<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Actions\Notify;
use App\Modules\Orders\Exceptions\ReviewConflict;
use App\Modules\Orders\Support\ReviewVisibility;
use Illuminate\Support\Facades\DB;

/**
 * Modération des avis et des réponses : MASQUER ou RÉTABLIR, avec catégorie et motif, historique append-only, journal d'audit. L'administrateur ne réécrit JAMAIS la note
 * ni le commentaire (déclencheur en base) et ne dépose jamais un avis. Une note négative seule n'est pas un motif de retrait : aucune catégorie ne la prévoit.
 * Les agrégats (moyenne, nombre) sont calculés sur les avis publiés : ils se recalculent d'eux-mêmes, l'historique reste intact.
 */
final class ModerateReviews
{
    public function __construct(private AdminAudit $audit, private Notify $notify) {}

    public function hide(User $admin, string $reviewId, string $target, string $category, string $reason): void
    {
        $this->audit->run($admin, 'review.hide', 'review', $reviewId, $this->label($reviewId), $reason, function () use ($admin, $reviewId, $target, $category, $reason) {
            if (! isset(ReviewVisibility::MODERATION_CATEGORIES[$category])) {
                throw new ReviewConflict('Choisissez un motif de retrait : une note négative, seule, n’en est pas un.');
            }
            $this->validateReason($reason);
            $this->change($admin, $reviewId, $target, 'hidden', $category, trim($reason));
        });
    }

    public function restore(User $admin, string $reviewId, string $target, string $reason): void
    {
        $this->audit->run($admin, 'review.restore', 'review', $reviewId, $this->label($reviewId), $reason, function () use ($admin, $reviewId, $target, $reason) {
            $this->validateReason($reason);
            $this->change($admin, $reviewId, $target, 'restored', null, trim($reason));
        });
    }

    private function change(User $admin, string $reviewId, string $target, string $action, ?string $category, string $reason): void
    {
        if (! in_array($target, ['review', 'reply'], true)) {
            throw new ReviewConflict('Contenu inconnu.');
        }
        $owner = DB::transaction(function () use ($admin, $reviewId, $target, $action, $category, $reason) {
            $r = DB::table('reviews')->where('id', $reviewId)->lockForUpdate()->first() ?? throw new ReviewConflict('Avis introuvable.');
            if ($target === 'review') {
                $table = 'reviews';
                $row = $r;
                $owner = $r->author_id;
            } else {
                $table = 'review_responses';
                $row = DB::table('review_responses')->where('review_id', $r->id)->lockForUpdate()->first() ?? throw new ReviewConflict('Cet avis n’a pas de réponse.');
                $owner = $row->author_id;
            }
            if ($action === 'hidden' && $row->hidden_at !== null) {
                throw new ReviewConflict('Ce contenu est déjà masqué.');
            }
            if ($action === 'restored' && $row->hidden_at === null) {
                throw new ReviewConflict('Ce contenu n’est pas masqué.');
            }
            if ($admin->getKey() === $owner) {
                throw new ReviewConflict('Vous ne pouvez pas modérer votre propre contenu.');
            }
            DB::table($table)->where($table === 'reviews' ? 'id' : 'review_id', $r->id)->update(['hidden_at' => $action === 'hidden' ? now() : null]);
            DB::table('review_moderations')->insert(['review_id' => $r->id, 'target' => $target, 'action' => $action, 'category' => $category, 'reason' => mb_substr($reason, 0, 1000), 'actor_id' => $admin->getKey(), 'created_at' => now()]);

            return $owner;
        });
        // Information du propriétaire : catégorie seulement (jamais la note interne du modérateur).
        ($this->notify)($owner, 'moderation_decision', 'review_mod:'.$reviewId.':'.$target.':'.$action.':'.now()->timestamp, $action === 'hidden'
            ? 'Votre '.($target === 'review' ? 'avis' : 'réponse').' est masqué : '.ReviewVisibility::MODERATION_CATEGORIES[$category]
            : 'Votre '.($target === 'review' ? 'avis' : 'réponse').' est de nouveau visible', null, 'notifications.index');
    }

    private function validateReason(string $reason): void
    {
        $n = mb_strlen(trim($reason));
        if ($n < 20 || $n > 1000) {
            throw new ReviewConflict('Indiquez le motif de la décision (20 à 1000 caractères).');
        }
    }

    private function label(string $reviewId): ?string
    {
        $ref = DB::table('reviews')->join('orders', 'orders.id', '=', 'reviews.order_id')->where('reviews.id', $reviewId)->value('orders.reference');

        return $ref === null ? null : 'Avis de la commande '.$ref;
    }
}
