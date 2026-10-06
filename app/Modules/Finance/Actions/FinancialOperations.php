<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\PaymentGateways;
use App\Integrations\Payments\ProviderStatus;
use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Finance\Support\FinanceConflict;
use App\Modules\Finance\Support\FinancialPolicy;
use App\Modules\Finance\Support\OrderFunds;
use App\Modules\Finance\Support\PayoutEligibility;
use App\Modules\Support\Support\PayoutHolds;
use App\Shared\CommandReceipts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opérations financières (remboursement, reversement) : demande, approbation, exécution, rapprochement.
 *
 * Principes (architecture §9–10) :
 *  - DÉCISION du support ≠ OPÉRATION financière ≠ EXÉCUTION : une décision ne « rembourse » rien. Une opération est demandée puis approuvée par d'AUTRES personnes.
 *  - Réservation à la demande dans le registre (le solde « escrow » d'une commande ne peut pas être engagé deux fois : verrou de commande + contrainte en base) ;
 *    libérée par une écriture CORRECTRICE si l'opération est refusée, annulée ou échoue ; confirmée seulement par un résultat établi.
 *  - Appel sortant : référence enregistrée AVANT l'appel, `approved → in_progress` conditionnel, appel hors transaction, JAMAIS rejoué à l'aveugle :
 *    délai dépassé / réponse illisible ⇒ « à vérifier » ; reprise = lecture du paiement chez le prestataire PUIS décision explicite.
 *  - Aucun reversement par API (rien n'est documenté par Genius Pay) : action manuelle distincte, avec référence externe, justificatif, auteur et contrôle explicite.
 *  - Réservé aux administrateurs (MFA, adresse vérifiée, confirmation récente à la route), jamais à une partie de la commande, journalisé.
 */
final class FinancialOperations
{
    public function __construct(private AdminAudit $audit, private FinanceLedger $ledger, private PaymentGateways $gateways) {}

    // ------------------------------------------------------------------ demandes

    public function requestRefund(User $actor, string $decisionId, ?int $amount, string $reason, string $key): string
    {
        $reason = trim($reason);
        $dec = DB::table('support_decisions as d')->join('support_cases as c', 'c.id', '=', 'd.case_id')->where('d.id', $decisionId)->first(['d.id', 'd.financial_need', 'c.order_id', 'c.reference as case_reference']);

        return $this->audit->run($actor, 'finance.refund.request', 'order', $dec?->order_id, $dec?->case_reference, $reason, function () use ($actor, $dec, $amount, $reason, $key) {
            $this->validateReason($reason);
            if ($dec === null) {
                throw new FinanceConflict('Décision du support introuvable.');
            }
            if (! in_array($dec->financial_need, ['refund', 'partial'], true)) {
                throw new FinanceConflict('Cette décision n’appelle pas de remboursement.');
            }
            $opKey = 'rf:'.substr(sha1($actor->getKey().'|'.$key), 0, 40);
            try {
                CommandReceipts::once($actor->getKey(), 'finance.request_refund', $key, ['d' => $dec->id, 'a' => $amount, 'r' => $reason], function () use ($actor, $dec, $amount, $reason, $opKey) {
                    $order = $this->lockOrder($dec->order_id);
                    $this->assertNotParty($actor, $order);
                    $payment = OrderFunds::confirmedPayment($order->id);
                    if ($payment === null) {
                        throw new FinanceConflict('Aucun paiement confirmé sur cette commande : rien à rembourser.');
                    }
                    if ($payment->provider !== 'genius_pay') {
                        throw new FinanceConflict('Paiement d’un ancien simulateur (retiré) : non remboursable.');
                    }
                    $simulated = (bool) $payment->is_simulated;
                    $escrow = OrderFunds::escrow($order->id, $simulated);
                    $amount ??= (int) $payment->amount_xof;
                    if ($amount <= 0 || $amount > $escrow) {
                        throw new FinanceConflict('Plafond remboursable : '.number_format($escrow, 0, ',', ' ').' FCFA (encaissé moins remboursements confirmés ou réservés et fonds déjà alloués).');
                    }
                    $scope = $amount === (int) $payment->amount_xof ? 'total' : 'partial';
                    if ($dec->financial_need === 'refund' && $scope !== 'total') {
                        throw new FinanceConflict('La décision prévoit un remboursement total : le montant doit être celui de l’encaissement.');
                    }
                    if (DB::table('financial_operations')->where('payment_id', $payment->id)->where('kind', 'refund')->whereIn('state', ['requested', 'approved', 'in_progress', 'to_verify'])->exists()) {
                        throw new FinanceConflict('Un remboursement est déjà ouvert pour ce paiement : une seule opération à la fois.');
                    }
                    $env = $simulated ? 'sandbox' : 'live';
                    $id = (string) Str::uuid();
                    $this->insertOperation($id, $order, 'refund', $scope, $amount, $env, $simulated, $payment->id, null, (int) $dec->id, null, $reason, $actor, $opKey,
                        FinancialPolicy::fingerprint('refund', $order->id, $payment->id, $amount, null, $env, $scope));
                    $this->ledger->post("op:{$id}:reserve", $order->id, $payment->id, 'refund_reserved', $simulated, ['escrow' => -$amount, 'refund_reserved' => $amount], $id);
                    $this->event($id, 'requested', $actor->getKey(), null, 'requested', 'Remboursement demandé : '.$amount.' FCFA ('.$scope.').', ['decision' => $dec->id]);

                    return $order->id;
                });
            } catch (UniqueConstraintViolationException) {
                throw new FinanceConflict('Un remboursement est déjà ouvert pour ce paiement : une seule opération à la fois.');
            }

            return (string) DB::table('financial_operations')->where('operation_key', $opKey)->value('id');
        });
    }

    public function requestPayout(User $actor, string $orderReference, string $reason, string $key): string
    {
        $reason = trim($reason);
        $order0 = DB::table('orders')->where('reference', $orderReference)->first(['id', 'reference']);

        return $this->audit->run($actor, 'finance.payout.request', 'order', $order0?->id, $orderReference, $reason, function () use ($actor, $order0, $reason, $key) {
            $this->validateReason($reason);
            if ($order0 === null) {
                throw new FinanceConflict('Commande introuvable.');
            }
            $opKey = 'po:'.substr(sha1($actor->getKey().'|'.$key), 0, 40);
            try {
                CommandReceipts::once($actor->getKey(), 'finance.request_payout', $key, ['o' => $order0->id, 'r' => $reason], function () use ($actor, $order0, $reason, $opKey) {
                    $order = $this->lockOrder($order0->id);
                    $this->assertNotParty($actor, $order);
                    $e = PayoutEligibility::evaluate($order);       // recalculée SOUS LE VERROU de commande (même verrou que l'ouverture d'un litige)
                    if (! $e['eligible']) {
                        throw new FinanceConflict('Reversement non éligible : '.implode(' ', array_map(fn ($r) => PayoutEligibility::REASONS[$r], $e['reasons'])));
                    }
                    $simulated = (bool) $e['simulated'];
                    $env = $simulated ? 'sandbox' : 'live';
                    $id = (string) Str::uuid();
                    $this->insertOperation($id, $order, 'payout', 'full', (int) $e['due'], $env, $simulated, $e['payment_id'], $e['beneficiary_id'], null,
                        ['base' => $e['base'], 'bp' => $e['bp'], 'commission' => $e['commission']], $reason, $actor, $opKey,
                        FinancialPolicy::fingerprint('payout', $order->id, $e['payment_id'], (int) $e['due'], $e['beneficiary_id'], $env, 'full'));
                    // Allocation + réservation en UN lot : escrow −base ; commission +commission ; reversement réservé +part freelance.
                    $this->ledger->post("op:{$id}:reserve", $order->id, $e['payment_id'], 'payout_reserved', $simulated,
                        ['escrow' => -$e['base'], 'platform_commission' => $e['commission'], 'payout_reserved' => $e['due']], $id);
                    $this->event($id, 'requested', $actor->getKey(), null, 'requested', 'Reversement demandé : part du freelance '.$e['due'].' FCFA (base '.$e['base'].', commission '.$e['commission'].').');

                    return $order->id;
                });
            } catch (UniqueConstraintViolationException) {
                throw new FinanceConflict('Un reversement est déjà demandé, en cours ou confirmé pour cette commande.');
            }

            return (string) DB::table('financial_operations')->where('operation_key', $opKey)->value('id');
        });
    }

    // ------------------------------------------------------------------ approbation

    public function approve(User $actor, string $opId, string $note): void
    {
        $this->decide($actor, $opId, $note, 'approved');
    }

    public function reject(User $actor, string $opId, string $note): void
    {
        $this->decide($actor, $opId, $note, 'rejected');
    }

    private function decide(User $actor, string $opId, string $note, string $decision): void
    {
        $note = trim($note);
        $op0 = $this->op($opId);
        $this->audit->run($actor, 'finance.operation.'.($decision === 'approved' ? 'approve' : 'reject'), 'financial_operation', $opId, $op0?->reference, $note, function () use ($actor, $opId, $note, $decision) {
            $this->validateReason($note);
            DB::transaction(function () use ($actor, $opId, $note, $decision) {
                [$order, $op] = $this->lockBoth($opId);
                $this->assertNotParty($actor, $order);
                if ($op->state !== 'requested') {
                    throw new FinanceConflict('Cette opération n’attend plus d’approbation.');
                }
                if ($op->requested_by === $actor->getKey()) {
                    throw new FinanceConflict('Le demandeur ne peut pas approuver sa propre demande.');
                }
                if (DB::table('financial_operation_approvals')->where('operation_id', $op->id)->where('approver_id', $actor->getKey())->exists()) {
                    throw new FinanceConflict('Vous avez déjà pris position sur cette opération.');
                }
                // L'approbation porte sur l'ACTION EXACTE : l'empreinte est recalculée depuis les caractéristiques actuelles.
                $fp = FinancialPolicy::fingerprint($op->kind, $op->order_id, $op->payment_id, (int) $op->amount_xof, $op->beneficiary_id, $op->environment, $op->scope);
                if (! hash_equals($op->fingerprint, $fp)) {
                    throw new FinanceConflict('L’empreinte de l’opération ne correspond plus : approbation refusée.');
                }
                if ($decision === 'approved' && $op->kind === 'payout') {
                    $this->assertPayoutStillPossible($order, $op);
                }
                DB::table('financial_operation_approvals')->insert(['operation_id' => $op->id, 'approver_id' => $actor->getKey(), 'decision' => $decision, 'fingerprint' => $fp, 'note' => mb_substr($note, 0, 500), 'created_at' => now()]);
                if ($decision === 'rejected') {
                    $this->close($op, $order, 'rejected', $actor->getKey(), 'rejected_by_approver', 'Opération refusée : '.$note);

                    return;
                }
                $n = DB::table('financial_operation_approvals')->where('operation_id', $op->id)->where('decision', 'approved')->count();
                $this->event($op->id, 'approval', $actor->getKey(), null, null, 'Approbation '.$n.'/'.FinancialPolicy::requiredApprovals((int) $op->amount_xof).'.');
                if ($n >= FinancialPolicy::requiredApprovals((int) $op->amount_xof)) {
                    $this->transition($op->id, 'requested', 'approved', ['approved_at' => now()]);
                    $this->event($op->id, 'approved', $actor->getKey(), 'requested', 'approved', 'Opération approuvée : exécution possible.');
                }
            });
        });
    }

    public function cancel(User $actor, string $opId, string $note): void
    {
        $note = trim($note);
        $op0 = $this->op($opId);
        $this->audit->run($actor, 'finance.operation.cancel', 'financial_operation', $opId, $op0?->reference, $note, function () use ($actor, $opId, $note) {
            $this->validateReason($note);
            DB::transaction(function () use ($actor, $opId, $note) {
                [$order, $op] = $this->lockBoth($opId);
                $this->assertNotParty($actor, $order);
                if (! in_array($op->state, ['requested', 'approved'], true)) {
                    throw new FinanceConflict('Seule une opération non encore exécutée peut être annulée.');
                }
                $this->close($op, $order, 'cancelled', $actor->getKey(), 'cancelled', 'Opération annulée : '.$note);
            });
        });
    }

    // ------------------------------------------------------------------ exécution : remboursement par API (total seulement)

    public function executeRefundViaApi(User $actor, string $opId): string
    {
        $op0 = $this->op($opId);

        return $this->audit->run($actor, 'finance.refund.execute_api', 'financial_operation', $opId, $op0?->reference, null, function () use ($actor, $opId) {
            // 1) revérifications (aucun appel, aucun verrou long)
            $op = $this->op($opId) ?? throw new FinanceConflict('Opération introuvable.');
            $payment = DB::table('payments')->where('id', $op->payment_id)->first();
            if ($op->kind !== 'refund' || $op->state !== 'approved') {
                throw new FinanceConflict('Seul un remboursement approuvé peut être exécuté.');
            }
            if ($op->scope !== 'total') {
                throw new FinanceConflict('Un remboursement partiel n’est pas exécuté par API : les règles des remboursements successifs et leur idempotence ne sont pas établies par la documentation. Effectuez-le chez le prestataire puis enregistrez-le manuellement.');
            }
            if ($payment === null || $payment->provider !== 'genius_pay' || $payment->provider_transaction_reference === null) {
                throw new FinanceConflict('Référence du paiement chez le prestataire absente : exécution impossible.');
            }
            $this->assertApiReady($op->environment);
            // 2) réclamation : `approved → in_progress` conditionnel + référence sortante enregistrée AVANT l'appel
            $claimed = DB::transaction(function () use ($actor, $opId) {
                [$order, $op] = $this->lockBoth($opId);
                $this->assertNotParty($actor, $order);
                if ($op->state !== 'approved') {
                    return false;
                }
                $n = DB::table('financial_operations')->where('id', $op->id)->where('state', 'approved')->update([
                    'state' => 'in_progress', 'execution_mode' => 'api', 'provider' => 'genius_pay', 'provider_reference' => 'RF-'.strtoupper(Str::random(14)),
                    'attempts' => $op->attempts + 1, 'executed_by' => $actor->getKey(), 'executed_at' => now(), 'row_version' => $op->row_version + 1, 'updated_at' => now(),
                ]);
                $n === 1 && $this->event($op->id, 'sent', $actor->getKey(), 'approved', 'in_progress', 'Remboursement total envoyé à Genius Pay (tentative '.($op->attempts + 1).').');

                return $n === 1;
            });
            if (! $claimed) {
                throw new FinanceConflict('Cette opération a déjà été prise en charge.');
            }
            // 3) appel HORS transaction ; 4) résultat enregistré
            $result = $this->gateways->forEnvironment($op->environment)->refund($payment->provider_transaction_reference, (int) $op->amount_xof, 'FreeCI '.$op->reference);
            match ($result->outcome) {
                'confirmed' => $this->settle($opId, 'api', $actor->getKey(), ['provider_refund_reference' => $result->refundReference]),
                'rejected' => DB::transaction(function () use ($opId, $result, $actor) {
                    [$order, $o] = $this->lockBoth($opId);
                    $this->close($o, $order, 'failed', $actor->getKey(), $result->code, 'Remboursement refusé par le prestataire ('.$result->code.') : aucun remboursement effectué.');
                }),
                'not_sent' => DB::transaction(function () use ($opId, $result, $actor) {      // aucun appel n'a été émis : on revient à « approuvée »
                    [, $o] = $this->lockBoth($opId);
                    $this->transition($o->id, 'in_progress', 'approved', ['execution_mode' => null, 'executed_by' => null, 'executed_at' => null, 'attempts' => max(0, $o->attempts - 1)]);
                    $this->event($o->id, 'not_sent', $actor->getKey(), 'in_progress', 'approved', 'Aucun appel émis ('.$result->code.') : l’opération reste approuvée.');
                }),
                default => $this->markUncertain($opId, $result->code),
            };

            return DB::table('financial_operations')->where('id', $opId)->value('state');
        });
    }

    // ------------------------------------------------------------------ exécution manuelle (reversement, ou remboursement hors API)

    /**
     * @param  array{external_reference: string, proof_note: string, amount_confirm: int|string, confirm: mixed}  $d
     */
    public function recordManual(User $actor, string $opId, array $d): void
    {
        $op0 = $this->op($opId);
        $this->audit->run($actor, 'finance.operation.record_manual', 'financial_operation', $opId, $op0?->reference, $d['proof_note'] ?? null, function () use ($actor, $opId, $d) {
            $ref = trim((string) ($d['external_reference'] ?? ''));
            $proof = trim((string) ($d['proof_note'] ?? ''));
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-\/ ]{3,79}$/', $ref)) {
                throw new FinanceConflict('Référence externe du transfert requise (4 à 80 caractères).');
            }
            if (mb_strlen($proof) < 10 || mb_strlen($proof) > 500) {
                throw new FinanceConflict('Décrivez le justificatif (10 à 500 caractères) : relevé, capture, numéro de transaction…');
            }
            if (empty($d['confirm'])) {
                throw new FinanceConflict('Confirmez explicitement que l’opération a bien été effectuée.');
            }
            DB::transaction(function () use ($actor, $opId, $d, $ref, $proof) {
                [$order, $op] = $this->lockBoth($opId);
                $this->assertNotParty($actor, $order);
                if ($op->state !== 'approved') {
                    throw new FinanceConflict('Seule une opération approuvée peut être enregistrée comme effectuée.');
                }
                if ((int) $d['amount_confirm'] !== (int) $op->amount_xof) {
                    throw new FinanceConflict('Le montant saisi ne correspond pas à celui de l’opération.');
                }
                if ($op->kind === 'payout') {
                    $this->assertPayoutStillPossible($order, $op);
                }
                if (DB::table('financial_operations')->where('kind', $op->kind)->where('state', 'confirmed')->where('external_reference', $ref)->exists()) {
                    throw new FinanceConflict('Cette référence externe est déjà rattachée à une autre opération confirmée.');
                }
                DB::table('financial_operations')->where('id', $op->id)->where('state', 'approved')->update([
                    'execution_mode' => 'manual', 'external_reference' => $ref, 'proof_note' => $proof, 'executed_by' => $actor->getKey(), 'executed_at' => now(), 'row_version' => $op->row_version + 1, 'updated_at' => now(),
                ]);
                $this->settle($opId, 'manual', $actor->getKey(), []);          // même transaction : jamais « exécutée » sans écriture de confirmation
            });
        });
    }

    // ------------------------------------------------------------------ rapprochement

    /**
     * Lecture du paiement chez le prestataire (jamais d'envoi) pour une opération API ouverte. `refunded` ⇒ confirmé ; sinon l'opération reste telle quelle.
     *
     * @return string confirmed | pending | uncertain | not_applicable
     */
    public function reconcile(string $opId): string
    {
        $op = $this->op($opId);
        if ($op === null || $op->kind !== 'refund' || $op->execution_mode !== 'api' || ! in_array($op->state, ['in_progress', 'to_verify'], true)) {
            return 'not_applicable';
        }
        $payment = DB::table('payments')->where('id', $op->payment_id)->first();
        DB::table('financial_operations')->where('id', $op->id)->update(['last_checked_at' => now()]);
        $v = $this->gateways->forEnvironment($op->environment)->verify((string) $payment->provider_transaction_reference);
        if ($v->status === ProviderStatus::Refunded && $op->scope === 'total') {
            // Un remboursement TOTAL au statut « refunded » : le montant est celui du paiement. Référence du remboursement non lue (non fournie par la lecture).
            $this->settle($opId, 'api', null, ['note' => 'Confirmé par lecture du statut du paiement chez le prestataire.']);

            return 'confirmed';
        }
        if ($op->state === 'in_progress' && $op->executed_at !== null && Carbon::parse($op->executed_at)->diffInMinutes(now()) >= 10) {
            $this->markUncertain($op->id, 'interrupted');                // envoi interrompu avant d'enregistrer la réponse : résultat inconnu
        }

        return $v->status === ProviderStatus::Succeeded ? 'pending' : 'uncertain';
    }

    /** Opération « à vérifier » : lecture FRAÎCHE obligatoire. Si le paiement est toujours « complété » chez le prestataire, le remboursement n'a pas eu lieu → échec constaté. */
    public function markNotRefunded(User $actor, string $opId, string $note): void
    {
        $this->afterFreshLookup($actor, $opId, $note, 'finance.refund.mark_failed', function ($op, $order, $actorId, $note) {
            $this->close($op, $order, 'failed', $actorId, 'not_refunded_confirmed', 'Échec constaté après lecture du paiement chez le prestataire (non remboursé) : '.$note);
        });
    }

    /** Reprise explicite d'un envoi : seulement après lecture fraîche montrant le paiement toujours « complété » ; l'opération repasse « approuvée » (l'appel est idempotent chez le prestataire). */
    public function resume(User $actor, string $opId, string $note): void
    {
        $this->afterFreshLookup($actor, $opId, $note, 'finance.refund.resume', function ($op, $order, $actorId, $note) {
            $this->transition($op->id, 'to_verify', 'approved', ['uncertain_reason' => null]);
            $this->event($op->id, 'resumed', $actorId, 'to_verify', 'approved', 'Reprise autorisée après vérification (paiement toujours « complété » chez le prestataire) : '.$note);
        });
    }

    private function afterFreshLookup(User $actor, string $opId, string $note, string $action, callable $apply): void
    {
        $note = trim($note);
        $op0 = $this->op($opId);
        $this->audit->run($actor, $action, 'financial_operation', $opId, $op0?->reference, $note, function () use ($actor, $opId, $note, $apply) {
            $this->validateReason($note);
            $op = $this->op($opId) ?? throw new FinanceConflict('Opération introuvable.');
            if ($op->kind !== 'refund' || $op->execution_mode !== 'api' || $op->state !== 'to_verify') {
                throw new FinanceConflict('Seule une opération « à vérifier » envoyée par API est concernée.');
            }
            $payment = DB::table('payments')->where('id', $op->payment_id)->first();
            $v = $this->gateways->forEnvironment($op->environment)->verify((string) $payment->provider_transaction_reference);
            if ($v->status === ProviderStatus::Refunded) {
                $this->settle($opId, 'api', null, ['note' => 'Confirmé par lecture du statut du paiement chez le prestataire.']);
                throw new FinanceConflict('Le prestataire indique que le paiement est remboursé : l’opération est confirmée, aucune autre action n’est nécessaire.');
            }
            if ($v->status !== ProviderStatus::Succeeded) {
                throw new FinanceConflict('Le statut du paiement chez le prestataire est indéterminé : impossible de conclure pour l’instant.');
            }
            DB::transaction(function () use ($actor, $opId, $note, $apply) {
                [$order, $o] = $this->lockBoth($opId);
                $this->assertNotParty($actor, $order);
                if ($o->state !== 'to_verify') {
                    throw new FinanceConflict('L’opération a changé entre-temps.');
                }
                $apply($o, $order, $actor->getKey(), $note);
            });
        });
    }

    // ------------------------------------------------------------------ règlement et clôtures (écritures du registre)

    /** @param array<string, mixed> $extra */
    public function settle(string $opId, string $mode, ?string $actorId, array $extra): void
    {
        DB::transaction(function () use ($opId, $mode, $actorId, $extra) {
            [$order, $op] = $this->lockBoth($opId);
            if ($op->state === 'confirmed') {
                return;                                              // idempotent : jamais deux confirmations ni deux lots
            }
            if (! in_array($op->state, ['in_progress', 'to_verify', 'approved'], true)) {
                throw new FinanceConflict('Cette opération ne peut plus être confirmée.');
            }
            $upd = ['state' => 'confirmed', 'execution_mode' => $mode, 'confirmed_at' => now(), 'executed_by' => $op->executed_by ?? $actorId ?? $op->requested_by, 'uncertain_reason' => null,
                'row_version' => $op->row_version + 1, 'updated_at' => now()];
            if (isset($extra['provider_refund_reference'])) {
                $upd['provider_refund_reference'] = $extra['provider_refund_reference'];
            }
            DB::table('financial_operations')->where('id', $op->id)->whereIn('state', ['in_progress', 'to_verify', 'approved'])->update($upd);
            $sim = (bool) $op->is_simulated;
            if ($op->kind === 'refund') {
                $this->ledger->post("op:{$op->id}:confirm", $op->order_id, $op->payment_id, 'refund_confirmed', $sim, ['refund_reserved' => -$op->amount_xof, 'external_payer' => $op->amount_xof], $op->id);
                $this->orderEvent($op->order_id, 'refund_confirmed', 'Remboursement confirmé ('.$op->reference.') : '.$op->amount_xof.' FCFA'.($sim ? ' (mode test, aucun argent réel).' : '.'));
                $this->releaseHoldIfDecided($op);
            } else {
                $this->ledger->post("op:{$op->id}:confirm", $op->order_id, $op->payment_id, 'payout_confirmed', $sim, ['payout_reserved' => -$op->amount_xof, 'external_freelancer' => $op->amount_xof], $op->id);
                $this->orderEvent($op->order_id, 'payout_confirmed', 'Reversement confirmé ('.$op->reference.') : '.$op->amount_xof.' FCFA'.($sim ? ' (mode test, aucun argent réel).' : '.'));
            }
            $this->event($op->id, 'confirmed', $actorId, $op->state, 'confirmed', $extra['note'] ?? ($mode === 'manual' ? 'Enregistré comme effectué manuellement (référence externe et justificatif conservés).' : 'Remboursement confirmé par le prestataire.'));
        });
    }

    /** Clôture sans effet financier (échec, refus, annulation) : la réservation est libérée par une écriture CORRECTRICE liée. */
    private function close(object $op, object $order, string $state, ?string $actorId, string $code, string $note): void
    {
        $this->transition($op->id, $op->state, $state, ['failure_code' => mb_substr($code, 0, 60), 'failed_at' => now()]);
        $this->ledger->reverse("op:{$op->id}:reserve", "op:{$op->id}:release", $op->id, $note);
        $this->event($op->id, $state, $actorId, $op->state, $state, $note);
        if ($state === 'failed') {
            $this->orderEvent($op->order_id, $op->kind === 'refund' ? 'refund_failed' : 'payout_failed', ($op->kind === 'refund' ? 'Remboursement' : 'Reversement').' non abouti ('.$op->reference.') : aucun montant n’a été versé.');
        }
    }

    private function markUncertain(string $opId, string $reason): void
    {
        $op = $this->op($opId);
        if ($op !== null && in_array($op->state, ['in_progress'], true)) {
            $this->transition($op->id, 'in_progress', 'to_verify', ['uncertain_reason' => mb_substr($reason, 0, 60)]);
            $this->event($op->id, 'uncertain', null, 'in_progress', 'to_verify', 'Résultat incertain ('.$reason.') : aucun renvoi automatique ; lecture du paiement chez le prestataire requise.');
        }
    }

    private function releaseHoldIfDecided(object $op): void
    {
        if ($op->support_decision_id === null) {
            return;
        }
        $case = DB::table('support_decisions')->where('id', $op->support_decision_id)->value('case_id');
        DB::table('payout_holds')->where('case_id', $case)->whereNull('released_at')->update(['released_at' => now(), 'released_by' => $op->executed_by ?? $op->requested_by, 'release_reason' => 'Remboursement décidé exécuté ('.$op->reference.')']);
    }

    // ------------------------------------------------------------------ utilitaires

    private function assertApiReady(string $environment): void
    {
        if (GeniusPayConfig::problems($environment) !== []) {
            throw new FinanceConflict('Configuration Genius Pay « '.$environment.' » incomplète : aucun appel émis.');
        }
        if ($environment === 'live') {
            if (! GeniusPayConfig::liveAuthorized()) {
                throw new FinanceConflict('Mode live non autorisé : aucun remboursement réel ne peut être émis.');
            }
            if ($this->gateways->forEnvironment('live')->merchantStatus() !== 'ok') {
                throw new FinanceConflict('Compte marchand live non vérifié : exécution refusée.');
            }
        }
    }

    /** Recontrôle SOUS VERROU avant d'approuver ou de constater un reversement : litige ou blocage ouverts depuis la demande. */
    private function assertPayoutStillPossible(object $order, object $op): void
    {
        if (PayoutHolds::isHeld($order->id) || $order->state === 'disputed') {
            throw new FinanceConflict('Un litige ou un blocage interne est ouvert sur cette commande : le reversement ne peut pas avancer (annulez l’opération ou attendez la décision).');
        }
        if (! DB::table('payout_beneficiaries')->where('id', $op->beneficiary_id)->where('status', 'verified')->exists()) {
            throw new FinanceConflict('Le bénéficiaire n’est plus vérifié et actif.');
        }
    }

    private function validateReason(string $reason): void
    {
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new FinanceConflict('Indiquez un motif (10 à 500 caractères).');
        }
    }

    private function assertNotParty(User $actor, object $order): void
    {
        if (in_array($actor->getKey(), [$order->client_id, $order->freelancer_id], true)) {
            throw new FinanceConflict('Vous êtes partie à cette commande : vous ne pouvez agir sur ses opérations financières ni les approuver.');
        }
    }

    private function lockOrder(string $orderId): object
    {
        return DB::table('orders')->where('id', $orderId)->lockForUpdate()->first() ?? throw new FinanceConflict('Commande introuvable.');
    }

    /** @return array{0: object, 1: object} [commande, opération] — ordre de verrouillage : commande, puis opération. */
    private function lockBoth(string $opId): array
    {
        $orderId = DB::table('financial_operations')->where('id', $opId)->value('order_id') ?? throw new FinanceConflict('Opération introuvable.');
        $order = $this->lockOrder($orderId);
        $op = DB::table('financial_operations')->where('id', $opId)->lockForUpdate()->first();

        return [$order, $op];
    }

    public function idByReference(string $reference): ?string
    {
        return DB::table('financial_operations')->where('reference', $reference)->value('id');
    }

    public function op(string $opId): ?object
    {
        return DB::table('financial_operations')->where('id', $opId)->first();
    }

    /** @param array<string, mixed> $set */
    private function transition(string $opId, string $from, string $to, array $set = []): void
    {
        $n = DB::table('financial_operations')->where('id', $opId)->where('state', $from)->update($set + ['state' => $to, 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);
        if ($n !== 1) {
            throw new FinanceConflict('L’opération a changé d’état : rechargez la page.');
        }
    }

    /** @param array<string, mixed>|null $meta */
    private function event(string $opId, string $type, ?string $actorId, ?string $from, ?string $to, ?string $note, ?array $meta = null): void
    {
        DB::table('financial_operation_events')->insert(['operation_id' => $opId, 'type' => $type, 'actor_id' => $actorId, 'from_state' => $from, 'to_state' => $to, 'note' => $note === null ? null : mb_substr($note, 0, 500),
            'meta' => $meta === null ? null : json_encode($meta), 'created_at' => now()]);
    }

    private function orderEvent(string $orderId, string $type, string $note): void
    {
        DB::table('order_events')->insert(['order_id' => $orderId, 'type' => $type, 'actor_id' => null, 'note' => $note, 'occurred_at' => now()]);
    }

    /** @param int|array{base:int,bp:int,commission:int}|null $payoutDetail  */
    private function insertOperation(string $id, object $order, string $kind, string $scope, int $amount, string $env, bool $simulated, string $paymentId, ?string $beneficiaryId, ?int $decisionId, int|array|null $payoutDetail, string $reason, User $actor, string $opKey, string $fingerprint): void
    {
        $seq = DB::selectOne("select nextval('financial_operation_reference_seq') as n")->n;
        $detail = is_array($payoutDetail) ? $payoutDetail : null;
        DB::table('financial_operations')->insert([
            'id' => $id, 'reference' => 'OP-'.now()->format('ym').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT), 'order_id' => $order->id, 'kind' => $kind, 'scope' => $scope, 'amount_xof' => $amount, 'currency' => 'XOF',
            'environment' => $env, 'is_simulated' => $simulated, 'payment_id' => $paymentId, 'beneficiary_id' => $beneficiaryId, 'support_decision_id' => $decisionId,
            'base_xof' => $detail['base'] ?? null, 'commission_bp' => $detail['bp'] ?? null, 'commission_xof' => $detail['commission'] ?? null,
            'state' => 'requested', 'fingerprint' => $fingerprint, 'operation_key' => $opKey, 'provider' => $kind === 'refund' ? 'genius_pay' : null,
            'requested_reason' => mb_substr($reason, 0, 500), 'requested_by' => $actor->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
