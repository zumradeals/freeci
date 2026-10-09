<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\ReviewConflict;
use App\Shared\CommandReceipts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `Orders\SubmitReview` (F36, restreint à la décision du porteur) : le CLIENT d'une commande dont il a EXPLICITEMENT validé la livraison, une fois la commande clôturée,
 * dépose UN avis (note 1–5 + commentaire). Un seul avis par commande (index unique) ; ni modification, ni suppression ; aucun administrateur ni autre utilisateur ne peut
 * déposer un avis à la place du client (l'auteur est toujours l'acteur, et c'est le client de la commande).
 *
 * Publication : une commande RÉELLE donne un avis public à partir de `max(dépôt, clôture + FREECI_REVIEW_PUBLICATION_DAYS)` (14 jours : paramètre PROVISOIRE, date figée au dépôt).
 * Une commande de TEST (sandbox) ou antérieure à l'environnement explicite ne produit JAMAIS d'avis public : l'avis est un « aperçu de test », visible des seules parties.
 */
final class SubmitReview
{
    public function __invoke(User $client, string $reference, int $rating, string $comment, string $operationKey): string
    {
        $comment = trim($comment);
        $min = (int) config('freeci.reviews.comment_min');
        $max = (int) config('freeci.reviews.comment_max');
        if ($rating < 1 || $rating > 5) {
            throw new ReviewConflict('La note doit être comprise entre 1 et 5.');
        }
        if (mb_strlen($comment) < $min || mb_strlen($comment) > $max) {
            throw new ReviewConflict("Le commentaire doit comporter de {$min} à {$max} caractères.");
        }
        $order = DB::table('orders')->where('reference', $reference)->where('client_id', $client->getKey())->first() ?? throw new OrderForbidden;

        try {
            CommandReceipts::once($client->getKey(), 'orders.submit_review', $operationKey, ['o' => $order->id, 'r' => $rating, 'c' => $comment], function () use ($client, $order, $rating, $comment) {
                $o = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
                if ($o->client_id !== $client->getKey()) {
                    throw new OrderForbidden;
                }
                if ($o->state !== 'closed' || $o->closure_reason !== 'validated') {
                    throw new ReviewConflict('Un avis n’est possible qu’après la validation explicite de la livraison et la clôture de la commande.');
                }
                // « Validation explicite » : la livraison a été validée PAR LE CLIENT (une validation décidée par le support n'ouvre pas d'avis).
                if (! DB::table('order_events')->where('order_id', $o->id)->where('type', 'validated')->where('actor_id', $client->getKey())->exists()) {
                    throw new ReviewConflict('Un avis n’est possible que si vous avez vous-même validé la livraison.');
                }
                if (DB::table('reviews')->where('order_id', $o->id)->exists()) {
                    throw new ReviewConflict('Vous avez déjà déposé un avis pour cette commande : un seul avis est possible.');
                }
                $isService = $o->origin === 'service';
                $public = $o->environment === 'live';
                $visibleAt = $public ? Carbon::parse($o->closed_at)->addDays((int) config('freeci.reviews.publication_days')) : now();
                DB::table('reviews')->insert([
                    'id' => (string) Str::uuid(), 'order_id' => $o->id, 'author_id' => $client->getKey(), 'subject_id' => $o->freelancer_id, 'origin' => $o->origin,
                    'service_id' => $isService ? $o->service_id : null, 'mission_id' => $o->origin === 'mission' ? $o->mission_id : null, 'rating' => $rating, 'comment' => $comment,
                    'counts_public' => $public, 'visible_at' => $visibleAt->greaterThan(now()) ? $visibleAt : now(), 'created_at' => now(),
                ]);

                return $o->id;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ReviewConflict('Vous avez déjà déposé un avis pour cette commande : un seul avis est possible.');
        }

        return (string) DB::table('reviews')->where('order_id', $order->id)->value('id');
    }
}
