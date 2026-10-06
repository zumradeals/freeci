<?php

namespace App\Modules\Support\Actions;

use App\Modules\Support\Exceptions\SupportConflict;
use Illuminate\Support\Facades\DB;

/**
 * EFFET d'une décision sur la COMMANDE (distinct de la décision et de toute suite financière). Appelé dans la transaction de la décision.
 * – continue : retour à l'état d'avant litige (rien n'est validé, rien n'est annulé) ;
 * – validate_delivery : la dernière livraison soumise est jugée conforme → commande validée puis clôturée (clôture commerciale, aucun reversement) ;
 * – cancel : annulation motivée après paiement. Aucun remboursement n'est exécuté ni déclaré exécuté.
 * L'échéance de livraison et le délai d'examen ne sont PAS modifiés automatiquement (aucune règle de neutralisation n'est définie : point à valider).
 */
final class DisputeEffects
{
    /** @return array{0: string, 1: string} [état avant, état après] */
    public function apply(object $case, string $outcome, string $staffId, string $reason): array
    {
        $o = DB::table('orders')->where('id', $case->order_id)->lockForUpdate()->first();
        if ($o === null || $o->state !== 'disputed') {
            throw new SupportConflict('La commande n’est plus « en litige » : la décision ne peut pas s’appliquer.');
        }
        $now = now();
        $base = ['row_version' => $o->row_version + 1, 'updated_at' => $now];
        $event = fn (string $type, string $to, ?string $note = null) => DB::table('order_events')->insert([
            'order_id' => $o->id, 'type' => $type, 'actor_id' => $staffId, 'from_state' => 'disputed', 'to_state' => $to, 'note' => $note, 'meta' => json_encode(['case' => $case->reference]), 'occurred_at' => $now,
        ]);

        switch ($outcome) {
            case 'continue':
                $to = (string) $case->order_state_before;
                if ($to === '' || $to === 'disputed') {
                    throw new SupportConflict('État d’origine de la commande introuvable.');
                }
                DB::table('orders')->where('id', $o->id)->update(['state' => $to] + $base);
                $event('dispute_resumed', $to);

                return ['disputed', $to];

            case 'validate_delivery':
                $latest = DB::table('deliveries')->where('order_id', $o->id)->where('state', 'submitted')->orderByDesc('version')->first();
                if ($latest === null || ! in_array($case->order_state_before, ['delivered', 'revision_requested'], true)) {
                    throw new SupportConflict('Cette décision suppose une livraison soumise en attente d’examen ou de correction.');
                }
                $event('dispute_validated', 'validated');
                DB::table('orders')->where('id', $o->id)->update(['state' => 'closed', 'validated_delivery_id' => $latest->id, 'validated_at' => $now, 'closure_reason' => 'validated', 'closed_at' => $now] + $base);
                DB::table('order_events')->insert(['order_id' => $o->id, 'type' => 'closed', 'actor_id' => null, 'from_state' => 'validated', 'to_state' => 'closed', 'occurred_at' => $now,
                    'note' => 'Clôture commerciale à la suite de la décision du support. Aucun reversement n’est déclenché ni confirmé par cette étape.']);

                return ['disputed', 'closed'];

            default:      // cancel
                DB::table('orders')->where('id', $o->id)->update(['state' => 'cancelled', 'closure_reason' => 'cancelled_after_payment', 'closure_note' => mb_substr($reason, 0, 500), 'closed_at' => $now] + $base);
                $event('dispute_cancelled', 'cancelled', 'Annulation motivée après paiement. Aucun remboursement n’est exécuté par cette étape.');

                return ['disputed', 'cancelled'];
        }
    }
}
