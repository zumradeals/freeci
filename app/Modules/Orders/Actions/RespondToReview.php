<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\ReviewConflict;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Réponse PUBLIQUE du freelance évalué : une seule par avis, immuable, possible seulement quand l'avis est visible (publié, ou aperçu de test pour une commande de test)
 * et non masqué. Elle ne remplace ni ne modifie jamais l'avis.
 */
final class RespondToReview
{
    public function __invoke(User $freelancer, string $reviewId, string $body): void
    {
        $body = trim($body);
        $max = (int) config('freeci.reviews.reply_max');
        if (mb_strlen($body) < 10 || mb_strlen($body) > $max) {
            throw new ReviewConflict("La réponse doit comporter de 10 à {$max} caractères.");
        }
        $r = DB::table('reviews')->where('id', $reviewId)->where('subject_id', $freelancer->getKey())->first() ?? throw new OrderForbidden;
        $visible = $r->counts_public ? ($r->hidden_at === null && now()->greaterThanOrEqualTo($r->visible_at)) : true;
        if (! $visible) {
            throw new ReviewConflict('Cet avis n’est pas encore publié ou a été masqué : vous ne pouvez pas y répondre pour le moment.');
        }
        if (DB::table('review_responses')->where('review_id', $r->id)->exists()) {
            throw new ReviewConflict('Vous avez déjà répondu à cet avis : une seule réponse est possible.');
        }
        try {
            DB::table('review_responses')->insert(['review_id' => $r->id, 'author_id' => $freelancer->getKey(), 'body' => $body, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            throw new ReviewConflict('Vous avez déjà répondu à cet avis : une seule réponse est possible.');
        }
    }
}
