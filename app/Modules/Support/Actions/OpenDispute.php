<?php

namespace App\Modules\Support\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Support\Contracts\PayoutExecution;
use App\Modules\Support\Exceptions\SupportConflict;
use App\Modules\Support\Support\CaseRules;
use App\Shared\CommandReceipts;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * `Orders\OpenDispute`, `Orders\RequestCancellation` et `Support\OpenClaim` (docs/02 §6).
 * LITIGE / ANNULATION : commande payée, reversement NON exécuté. La commande passe à « en litige » (les actions de travail sont suspendues par la
 * machine d'états) et un blocage INTERNE des reversements est enregistré. RÉCLAMATION : reversement déjà exécuté — aucun blocage, aucun état de
 * commande modifié, aucune promesse de récupération.
 */
final class OpenDispute
{
    public function __construct(private CaseStore $store, private PayoutExecution $payouts) {}

    /** @return array{0: string, 1: bool} [référence du dossier, répétition] */
    public function __invoke(User $user, string $orderReference, string $kind, string $reason, string $operationKey): array
    {
        if (! in_array($kind, CaseRules::DISPUTE_KINDS, true)) {
            throw new SupportConflict('Type de dossier inconnu.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 20 || mb_strlen($reason) > (int) config('freeci.support.body_max')) {
            throw ValidationException::withMessages(['reason' => 'Expliquez le motif (20 à '.config('freeci.support.body_max').' caractères) : les deux parties le verront.']);
        }
        $order = DB::table('orders')->where('reference', $orderReference)->where(fn ($q) => $q->where('client_id', $user->getKey())->orWhere('freelancer_id', $user->getKey()))->first(['id']);
        if ($order === null) {
            throw new OrderForbidden;
        }

        [$caseId, $replayed] = CommandReceipts::once($user->getKey(), 'support.open_dispute', $operationKey, ['r' => $orderReference, 'k' => $kind, 'm' => $reason],
            function () use ($user, $order, $kind, $reason) {
                $o = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();          // même verrou que le futur reversement
                if ($o->client_id !== $user->getKey() && $o->freelancer_id !== $user->getKey()) {
                    throw new OrderForbidden;
                }
                $other = $o->client_id === $user->getKey() ? $o->freelancer_id : $o->client_id;
                $executed = $this->payouts->executed($o->id);

                if ($kind === 'claim') {
                    if (! $executed) {
                        throw new SupportConflict('Aucun reversement n’a été exécuté pour cette commande : ouvrez un litige, qui bloque le reversement.');
                    }
                } else {
                    if ($executed) {
                        throw new SupportConflict('Le reversement a déjà été envoyé : un litige ne peut plus le bloquer. Déposez une réclamation.');
                    }
                    $allowed = $kind === 'dispute' ? CaseRules::DISPUTE_STATES : CaseRules::CANCEL_STATES;
                    if (! in_array($o->state, $allowed, true)) {
                        throw new SupportConflict($kind === 'dispute'
                            ? 'Un litige n’est possible que sur une commande payée dont le reversement n’est pas exécuté (travail en cours, livrée, validée ou clôturée).'
                            : 'Une annulation après paiement ne se demande que sur une commande payée en attente de brief, en cours, livrée ou en correction.');
                    }
                    if (DB::table('support_cases')->where('order_id', $o->id)->whereIn('kind', ['dispute', 'cancellation'])->whereIn('status', CaseRules::LIVE)->exists()) {
                        throw new SupportConflict('Un dossier de litige ou d’annulation est déjà ouvert pour cette commande.');
                    }
                }

                $caseId = $this->store->create([
                    'kind' => $kind, 'requester_id' => $user->getKey(), 'counterparty_id' => $kind === 'claim' ? null : $other, 'order_id' => $o->id, 'target_type' => 'order', 'target_id' => $o->id, 'target_label' => $o->reference,
                    'subject' => mb_substr(CaseRules::KINDS[$kind].' — commande '.$o->reference, 0, 160), 'order_state_before' => $kind === 'claim' ? null : $o->state, 'priority' => 'high',
                ]);
                $ref = DB::table('support_cases')->where('id', $caseId)->value('reference');
                $this->store->message($caseId, $user->getKey(), $kind === 'claim' ? 'requester' : 'parties', $reason);
                $this->store->event($caseId, 'opened', $user->getKey(), $kind === 'claim' ? 'requester' : 'parties', CaseRules::KINDS[$kind].' ouvert(e)');

                if ($kind !== 'claim') {
                    DB::table('payout_holds')->insert(['order_id' => $o->id, 'case_id' => $caseId, 'reason' => 'Dossier '.$ref.' ouvert', 'created_at' => now()]);
                    DB::table('orders')->where('id', $o->id)->update(['state' => 'disputed', 'row_version' => $o->row_version + 1, 'updated_at' => now()]);
                    DB::table('order_events')->insert(['order_id' => $o->id, 'type' => 'dispute_opened', 'actor_id' => $user->getKey(), 'from_state' => $o->state, 'to_state' => 'disputed',
                        'meta' => json_encode(['case' => $ref, 'kind' => $kind]), 'occurred_at' => now()]);
                    $this->store->notify($other, 'dispute_update', 'case_opened:'.$caseId.':'.$other, $kind === 'dispute' ? 'Un litige est ouvert sur votre commande' : 'Une annulation après paiement est demandée sur votre commande', $ref);
                }
                $this->store->notify($user->getKey(), 'dispute_update', 'case_opened:'.$caseId.':'.$user->getKey(), 'Votre dossier est enregistré : l’équipe va le prendre en charge', $ref);

                return $caseId;
            });

        return [DB::table('support_cases')->where('id', $caseId)->value('reference'), $replayed];
    }
}
