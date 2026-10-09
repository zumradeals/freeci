<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Support\ReviewVisibility;
use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lectures des avis. Public = `ReviewVisibility::published` (commande réelle, date atteinte, non masqué). Les agrégats sont CALCULÉS à la lecture sur ces seuls avis :
 * un masquage ou un rétablissement les met à jour sans rien supprimer. Les avis issus d'un service et d'une mission sont comptés SÉPARÉMENT.
 */
final class ReviewQueries
{
    public static function format(float|int|string|null $avg): ?string
    {
        return $avg === null ? null : number_format((float) $avg, 1, ',', ' ');
    }

    /**
     * Statistiques par SERVICE : seuls les avis issus de ce service (jamais ceux d'une mission).
     *
     * @param  list<string>  $serviceIds
     * @return array<string, array{count: int, avg: ?string}>
     */
    public function forServices(array $serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }
        $rows = ReviewVisibility::published(DB::table('reviews'))->where('origin', 'service')->whereIn('service_id', $serviceIds)
            ->selectRaw('service_id as k, count(*) as c, avg(rating) as a')->groupBy('service_id')->get();

        return $rows->mapWithKeys(fn ($r) => [$r->k => ['count' => (int) $r->c, 'avg' => self::format($r->a)]])->all();
    }

    /**
     * Statistiques par PROFIL (identifiant du profil) : tous les avis publiés du freelance + détail par origine.
     *
     * @param  list<string>  $profileIds
     * @return array<string, array{count: int, avg: ?string, service: array{count: int, avg: ?string}, mission: array{count: int, avg: ?string}}>
     */
    public function forProfiles(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }
        $rows = ReviewVisibility::published(DB::table('reviews'))->join('freelance_profiles as p', 'p.user_id', '=', 'reviews.subject_id')->whereIn('p.id', $profileIds)
            ->selectRaw("p.id as k, count(*) as c, avg(rating) as a, count(*) filter (where origin = 'service') as cs, avg(rating) filter (where origin = 'service') as avs, count(*) filter (where origin = 'mission') as cm, avg(rating) filter (where origin = 'mission') as avm")
            ->groupBy('p.id')->get();

        return $rows->mapWithKeys(fn ($r) => [$r->k => [
            'count' => (int) $r->c, 'avg' => self::format($r->a), 'service' => ['count' => (int) $r->cs, 'avg' => self::format($r->avs)], 'mission' => ['count' => (int) $r->cm, 'avg' => self::format($r->avm)],
        ]])->all();
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> avis publiés d'un service (origine « service » uniquement) */
    public function pageForService(string $serviceId, int $page = 1): LengthAwarePaginator
    {
        $q = ReviewVisibility::published(DB::table('reviews'))->where('origin', 'service')->where('service_id', $serviceId)->orderByDesc('visible_at')->orderByDesc('reviews.id');

        return $this->hydrate($q->paginate((int) config('freeci.reviews.per_page'), ['reviews.*'], 'avis', $page), false);
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> tous les avis publiés d'un freelance (service ET mission, étiquetés) */
    public function pageForProfile(string $profileId, int $page = 1): LengthAwarePaginator
    {
        $userId = DB::table('freelance_profiles')->where('id', $profileId)->value('user_id');
        $q = ReviewVisibility::published(DB::table('reviews'))->where('subject_id', $userId)->orderByDesc('visible_at')->orderByDesc('reviews.id');

        return $this->hydrate($q->paginate((int) config('freeci.reviews.per_page'), ['reviews.*'], 'avis', $page), true);
    }

    /** @param LengthAwarePaginator<int, object> $p */
    private function hydrate(LengthAwarePaginator $p, bool $withSource): LengthAwarePaginator
    {
        $ids = $p->getCollection()->pluck('id')->all();
        $replies = DB::table('review_responses')->whereIn('review_id', $ids)->whereNull('hidden_at')->get()->keyBy('review_id');
        // Titre de service affiché seulement si le service est encore publié (aucun titre d'un service retiré).
        $titles = $withSource ? DB::table('services')->where('status', 'published')->whereIn('id', $p->getCollection()->pluck('service_id')->filter()->all())->pluck('title', 'id') : collect();

        return $p->through(fn ($r) => [
            'id' => $r->id, 'rating' => (int) $r->rating, 'comment' => $r->comment, 'when' => Dates::short(Carbon::parse($r->visible_at)),
            'source' => $r->origin === 'mission' ? 'À la suite d’une mission' : ($r->origin === 'offer' ? 'À la suite d’une offre personnalisée' : ($withSource ? (isset($titles[$r->service_id]) ? 'Service : '.$titles[$r->service_id] : 'Service') : null)),
            'reply' => isset($replies[$r->id]) ? ['id' => $replies[$r->id]->id, 'body' => $replies[$r->id]->body, 'when' => Dates::short(Carbon::parse($replies[$r->id]->created_at))] : null,
        ]);
    }

    /** Panneau d'avis par RÉFÉRENCE de commande : null si l'utilisateur n'est pas partie à la commande (réponse 404 côté contrôleur). */
    public function panelForReference(string $reference, User $viewer): ?array
    {
        $o = DB::table('orders')->where('reference', $reference)->where(fn ($q) => $q->where('client_id', $viewer->getKey())->orWhere('freelancer_id', $viewer->getKey()))->first(['id', 'reference']);

        return $o === null ? null : ($this->panel($o->id, $viewer) ?? ['reference' => $o->reference, 'closed' => false]);
    }

    /**
     * Panneau « Avis » d'une commande, vu par une partie. Jamais d'action possible pour un autre utilisateur.
     *
     * @return array<string, mixed>|null null si la commande n'est pas (encore) concernée
     */
    public function panel(string $orderId, User $viewer): ?array
    {
        $o = DB::table('orders')->where('id', $orderId)->first();
        if ($o === null || ! in_array($viewer->getKey(), [$o->client_id, $o->freelancer_id], true)) {
            return null;
        }
        $isClient = $viewer->getKey() === $o->client_id;
        $closedValidated = $o->state === 'closed' && $o->closure_reason === 'validated';
        if (! $closedValidated) {
            return null;
        }
        $explicit = DB::table('order_events')->where('order_id', $o->id)->where('type', 'validated')->where('actor_id', $o->client_id)->exists();
        $r = DB::table('reviews')->where('order_id', $o->id)->first();
        $test = $o->environment !== 'live';
        $public = $r !== null && $r->counts_public && $r->hidden_at === null && now()->greaterThanOrEqualTo($r->visible_at);
        $reply = $r === null ? null : DB::table('review_responses')->where('review_id', $r->id)->first();

        $panel = [
            'reference' => $o->reference, 'isClient' => $isClient, 'test' => $test, 'canSubmit' => $isClient && $explicit && $r === null,
            'eligibleReason' => $explicit ? null : 'La livraison a été validée par une décision du support : aucun avis n’est ouvert (règle provisoire).',
            'review' => null, 'reply' => null, 'canReply' => false,
        ];
        if ($r !== null) {
            // Le freelance ne voit l'avis qu'une fois visible (publié) ; pour une commande de test, l'aperçu est visible des deux parties.
            $seen = $isClient || $test || $public;
            $panel['review'] = ! $seen ? ['pending' => true] : [
                'id' => $r->id, 'rating' => (int) $r->rating, 'comment' => $r->comment, 'visibleAt' => Dates::short(Carbon::parse($r->visible_at)), 'public' => $public, 'hidden' => $r->hidden_at !== null,
                'waiting' => $r->counts_public && $r->hidden_at === null && ! $public,
            ];
            $panel['reply'] = $reply === null || ! $seen ? null : ['body' => $reply->body, 'when' => Dates::short(Carbon::parse($reply->created_at)), 'hidden' => $reply->hidden_at !== null];
            $panel['canReply'] = ! $isClient && $seen && $reply === null && ($test || $public);
        }

        return $panel;
    }
}
