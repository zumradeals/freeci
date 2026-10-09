<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\PaymentGateways;
use App\Integrations\Payments\ProviderStatus;
use App\Integrations\Payments\Verification;
use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Finance\Models\ReconciliationCase;
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
 *  - DÉCISION du support ≠ OPÉRATION financière ≠ RÉSULTAT D'EXÉCUTION : une décision ne « rembourse » rien. Un SEUL administrateur peut préparer, confirmer
 *    (explicitement, après récapitulatif) et exécuter une opération : aucun second approbateur, aucun seuil de double validation (décision du porteur).
 *  - Réservation à la demande dans le registre (le solde « escrow » d'une commande ne peut pas être engagé deux fois : verrou de commande + contrainte en base) ;
 *    libérée par une écriture CORRECTRICE si l'opération est refusée, annulée ou échoue ; confirmée seulement par un résultat établi.
 *  - Appel sortant : référence enregistrée AVANT l'appel, `approved → in_progress` conditionnel, appel hors transaction, JAMAIS rejoué à l'aveugle :
 *    délai dépassé / réponse illisible / incohérente ⇒ « à vérifier », fonds réservés. Un statut lu chez le prestataire (« refunded », « completed ») n'établit ni le
 *    montant remboursé, ni son rattachement, ni un échec : seul un RAPPROCHEMENT MANUEL documenté (référence + justificatif) confirme ou libère l'opération.
 *  - Aucun reversement par API (rien n'est documenté par Genius Pay) : action manuelle distincte, avec référence externe, justificatif, auteur et contrôle explicite.
 *  - Réservé aux administrateurs (MFA, adresse vérifiée, confirmation récente à la route), journalisé. Conflit d'intérêts : un administrateur partie à une commande RÉELLE
 *    ne peut pas agir dessus ; sur une commande de TEST (sandbox) il le peut, avec indication explicite et audit.
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
                    $this->assertNoConflictOfInterest($actor, $order);
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
                    $this->assertNoConflictOfInterest($actor, $order);
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

    /** Confirmation EXPLICITE du récapitulatif (montant, bénéficiaire, environnement) : le même administrateur que le demandeur peut la donner. */
    public function approve(User $actor, string $opId, bool $confirmed, string $note = ''): void
    {
        $this->decide($actor, $opId, $note, 'approved', $confirmed);
    }

    public function reject(User $actor, string $opId, string $note): void
    {
        $this->decide($actor, $opId, $note, 'rejected', true);
    }

    private function decide(User $actor, string $opId, string $note, string $decision, bool $confirmed): void
    {
        $note = trim($note);
        $op0 = $this->op($opId);
        $this->audit->run($actor, 'finance.operation.'.($decision === 'approved' ? 'confirm' : 'reject'), 'financial_operation', $opId, $op0?->reference, $note, function () use ($actor, $opId, $note, $decision, $confirmed) {
            if ($decision === 'rejected') {
                $this->validateReason($note);
            } elseif (! $confirmed) {
                throw new FinanceConflict('Confirmez explicitement le récapitulatif (montant, bénéficiaire, environnement) avant de poursuivre.');
            }
            DB::transaction(function () use ($actor, $opId, $note, $decision) {
                [$order, $op] = $this->lockBoth($opId);
                $this->assertNoConflictOfInterest($actor, $order);
                if ($op->state !== 'requested') {
                    throw new FinanceConflict('Cette opération n’attend plus de confirmation.');
                }
                if (DB::table('financial_operation_approvals')->where('operation_id', $op->id)->where('approver_id', $actor->getKey())->exists()) {
                    throw new FinanceConflict('Cette opération a déjà été confirmée ou refusée.');
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
                $this->transition($op->id, 'requested', 'approved', ['approved_at' => now()]);
                $this->event($op->id, 'approved', $actor->getKey(), 'requested', 'approved', 'Récapitulatif confirmé explicitement : exécution ou enregistrement possible.');
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
                $this->assertNoConflictOfInterest($actor, $order);
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
                $this->assertNoConflictOfInterest($actor, $order);
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
                $this->assertNoConflictOfInterest($actor, $order);
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
     * Lecture du paiement chez le prestataire (jamais d'envoi, jamais de confirmation, jamais de libération des fonds). Le statut lu n'est qu'une INFORMATION :
     *  - « refunded » n'établit ni le montant remboursé (la lecture ne le fournit pas), ni son rattachement à notre demande (un remboursement a pu être fait ailleurs) ;
     *  - « completed » après un délai dépassé n'établit pas un échec (traitement possiblement en cours, asynchrone, ou réponse perdue).
     * Dans les deux cas l'opération reste « à vérifier » avec ses fonds réservés ; seul le rapprochement manuel documenté (`reconcileManually`) conclut.
     *
     * @return string confirmed_on_provider_proof | provider_reports_refunded | pending | uncertain | not_applicable
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
        if ($op->state === 'in_progress' && $op->executed_at !== null && Carbon::parse($op->executed_at)->diffInMinutes(now()) >= 10) {
            $this->markUncertain($op->id, 'interrupted');                // envoi interrompu avant d'enregistrer la réponse : résultat inconnu
            $op = $this->op($opId);
        }
        if ($v->status === ProviderStatus::Refunded && $this->providerProvesTotalRefund($op, $payment, $v)) {
            // Preuve du prestataire (lecture indépendante) : ce paiement, intégralement remboursé chez lui, est celui de CETTE opération totale. Confirmation sans intervention.
            $this->settle($op->id, 'api', null, ['provider_proof' => ['status' => $v->status->value, 'amount_xof' => $v->amountXof, 'currency' => $v->currency, 'transaction' => $v->transactionReference, 'environment' => $v->environment, 'read_at' => now()->toIso8601String()], 'note' => 'Confirmé sur preuve du prestataire : lecture du paiement « remboursé » pour le montant total ('.$op->amount_xof.' FCFA), même transaction, même environnement ; aucune référence de remboursement fournie.']);
            DB::table('reconciliation_cases')->where('payment_id', $op->payment_id)->whereIn('reason', ['refund_unproven_provider_refunded', 'refunded_by_provider'])->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolution_note' => 'Clos automatiquement : remboursement total confirmé sur preuve du prestataire ('.$op->reference.').']);

            return 'confirmed_on_provider_proof';
        }
        if ($v->status === ProviderStatus::Refunded) {
            if ($op->state === 'in_progress') {
                $this->markUncertain($op->id, 'provider_reports_refunded');
            } elseif ($op->uncertain_reason !== 'provider_reports_refunded') {
                DB::table('financial_operations')->where('id', $op->id)->where('state', 'to_verify')->update(['uncertain_reason' => 'provider_reports_refunded', 'updated_at' => now()]);
                $this->event($op->id, 'provider_reports_refunded', null, 'to_verify', 'to_verify', 'Le prestataire indique « remboursé » : ni le montant ni le rattachement à cette demande ne sont établis. Rapprochement manuel requis ; rien n’est confirmé.');
            }
            ReconciliationCase::query()->firstOrCreate(['order_id' => $op->order_id, 'payment_id' => $op->payment_id, 'reason' => 'refund_unproven_provider_refunded'], ['details' => ['operation' => $op->reference]]);

            return 'provider_reports_refunded';
        }

        return $v->status === ProviderStatus::Succeeded ? 'pending' : 'uncertain';
    }

    /**
     * La lecture du paiement chez le prestataire PROUVE le remboursement total quand, ensemble : l'opération est un remboursement TOTAL par API ; le paiement est celui
     * que FreeCI a confirmé et c'est la même transaction ; le prestataire dit « remboursé » ; le montant lu égale celui encaissé et celui de l'opération ; la devise (si
     * fournie) est XOF ; l'environnement est celui de l'opération ; aucun autre remboursement n'est confirmé sur ce paiement. Tout élément manquant ou discordant : pas de preuve.
     */
    private function providerProvesTotalRefund(object $op, object $payment, Verification $v): bool
    {
        if ($op->kind !== 'refund' || $op->scope !== 'total' || $op->execution_mode !== 'api' || ! in_array($op->state, ['in_progress', 'to_verify'], true)) {
            return false;
        }
        $confirmed = OrderFunds::confirmedPayment($op->order_id);
        if ($confirmed === null || $confirmed->id !== $payment->id || $payment->provider_transaction_reference === null) {
            return false;
        }
        if ($v->transactionReference !== $payment->provider_transaction_reference || $v->environment !== $op->environment || ($v->currency !== null && $v->currency !== 'XOF')) {
            return false;
        }
        if ($v->amountXof === null || $v->amountXof !== (int) $payment->amount_xof || (int) $op->amount_xof !== (int) $payment->amount_xof) {
            return false;
        }

        return ! DB::table('financial_operations')->where('payment_id', $payment->id)->where('kind', 'refund')->where('state', 'confirmed')->where('id', '!=', $op->id)->exists();
    }

    /**
     * Rapprochement MANUEL d'une opération « à vérifier », documenté par l'administrateur (référence + justificatif + confirmation explicite). C'est le seul moyen
     * de conclure : l'API ne fournit pas d'élément fiable (montant remboursé, rattachement, preuve d'échec) à partir d'une simple lecture.
     *  - `refunded` : le remboursement a été constaté (référence du remboursement ou du tableau de bord) pour le montant exact de l'opération → confirmé ;
     *  - `not_refunded` : l'absence de remboursement a été constatée → échoué, réservation libérée par écriture correctrice. Refusé si le prestataire indique « remboursé ».
     *
     * @param  array{reference: string, proof: string, amount_confirm?: int|string, confirm: mixed}  $d
     */
    public function reconcileManually(User $actor, string $opId, string $outcome, array $d): void
    {
        $op0 = $this->op($opId);
        $this->audit->run($actor, 'finance.refund.reconcile_manually', 'financial_operation', $opId, $op0?->reference, $d['proof'] ?? null, function () use ($actor, $opId, $outcome, $d) {
            $ref = trim((string) ($d['reference'] ?? ''));
            $proof = trim((string) ($d['proof'] ?? ''));
            if (! in_array($outcome, ['refunded', 'not_refunded'], true)) {
                throw new FinanceConflict('Résultat constaté inconnu.');
            }
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-\/ ]{3,79}$/', $ref)) {
                throw new FinanceConflict('Référence requise (4 à 80 caractères) : référence du remboursement, ou du contrôle effectué dans le tableau de bord du prestataire.');
            }
            if (mb_strlen($proof) < 20 || mb_strlen($proof) > 500) {
                throw new FinanceConflict('Décrivez le justificatif (20 à 500 caractères) : ce que vous avez constaté, où et quand.');
            }
            if (empty($d['confirm'])) {
                throw new FinanceConflict('Confirmez explicitement votre constat.');
            }
            $op = $this->op($opId) ?? throw new FinanceConflict('Opération introuvable.');
            if ($op->kind !== 'refund' || $op->execution_mode !== 'api' || ! in_array($op->state, ['to_verify', 'in_progress'], true)) {
                throw new FinanceConflict('Seule une opération de remboursement envoyée par API et « à vérifier » est concernée.');
            }
            if ($op->state === 'in_progress' && ($op->executed_at === null || Carbon::parse($op->executed_at)->diffInMinutes(now()) < 10)) {
                throw new FinanceConflict('Un envoi est peut-être encore en cours : attendez 10 minutes avant de le rapprocher.');
            }
            $lookup = null;
            if ($outcome === 'not_refunded') {
                $payment = DB::table('payments')->where('id', $op->payment_id)->first();
                $lookup = $this->gateways->forEnvironment($op->environment)->verify((string) $payment->provider_transaction_reference)->status;
                if ($lookup === ProviderStatus::Refunded) {
                    throw new FinanceConflict('Le prestataire indique « remboursé » : l’absence de remboursement ne peut pas être constatée. Identifiez le montant et la référence du remboursement, puis constatez-le.');
                }
            } elseif ((int) ($d['amount_confirm'] ?? 0) !== (int) $op->amount_xof) {
                throw new FinanceConflict('Retapez le montant exact effectivement remboursé : il doit être égal à celui de l’opération.');
            }
            DB::transaction(function () use ($actor, $opId, $outcome, $ref, $proof, $lookup) {
                [$order, $o] = $this->lockBoth($opId);
                $this->assertNoConflictOfInterest($actor, $order);
                if (! in_array($o->state, ['to_verify', 'in_progress'], true)) {
                    throw new FinanceConflict('L’opération a changé entre-temps.');
                }
                DB::table('financial_operations')->where('id', $o->id)->update(['reconciled_by' => $actor->getKey(), 'reconciled_at' => now(), 'reconciliation_reference' => $ref,
                    'reconciliation_proof' => mb_substr($proof, 0, 500), 'row_version' => $o->row_version + 1, 'updated_at' => now()]);
                if ($outcome === 'refunded') {
                    $this->settle($opId, 'api', $actor->getKey(), ['note' => 'Remboursement CONSTATÉ manuellement (référence '.$ref.') : '.$proof]);
                } else {
                    $this->close($this->op($opId), $order, 'failed', $actor->getKey(), 'not_refunded_declared',
                        'Absence de remboursement CONSTATÉE manuellement (référence '.$ref.'; lecture du prestataire : '.($lookup?->value ?? 'indéterminée').', non probante seule) : '.$proof);
                }
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
            if (isset($extra['provider_proof'])) {
                $upd['provider_proof_at'] = now();
                $upd['provider_proof'] = json_encode($extra['provider_proof']);
            }
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

    /**
     * Conflit d'intérêts. Commande RÉELLE dont l'administrateur est client ou freelance : bloqué (il ne se rembourse ni ne se verse lui-même). Commande de TEST (sandbox) :
     * autorisé pour permettre de tester le parcours avec son propre compte — avec une ligne d'audit explicite. Cela n'impose jamais un second administrateur
     * pour les commandes ordinaires des autres utilisateurs.
     */
    private function assertNoConflictOfInterest(User $actor, object $order): void
    {
        if (! in_array($actor->getKey(), [$order->client_id, $order->freelancer_id], true)) {
            return;
        }
        if ($order->environment === 'test') {
            $this->audit->record($actor, 'finance.sandbox_party', 'order', $order->id, $order->reference, null, 'done', 'Administrateur partie à une commande de TEST (sandbox) : parcours financier autorisé, aucun argent réel.');

            return;
        }
        throw new FinanceConflict('Conflit d’intérêts : vous êtes client ou freelance de cette commande RÉELLE. Un administrateur ne peut pas rembourser, verser ni confirmer une opération sur sa propre commande réelle ; ce blocage ne concerne pas les commandes des autres utilisateurs ni les commandes de test (sandbox).');
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
