<?php

namespace App\Modules\Support\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Support\ReviewVisibility;
use App\Modules\Support\Exceptions\SupportConflict;
use App\Modules\Support\Support\CaseRules;
use App\Shared\CommandReceipts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Contacter le support (depuis l'espace connecté ou une commande) et signaler un profil, un service, une mission ou un message.
 * Un compte suspendu peut toujours contacter le support. Le signalé n'est jamais informé ; le demandeur suit son dossier.
 */
final class OpenCase
{
    public function __construct(private CaseStore $store) {}

    /** @return array{0: string, 1: bool} [référence, répétition] */
    public function support(User $user, string $subject, string $body, string $category, ?string $orderReference, string $operationKey): array
    {
        [$subject, $body] = $this->text($subject, $body);
        if (! isset(CaseRules::SUPPORT_CATEGORIES[$category])) {
            throw ValidationException::withMessages(['category' => 'Choisissez un sujet.']);
        }
        $order = null;
        if ($orderReference !== null && $orderReference !== '') {
            $order = DB::table('orders')->where('reference', $orderReference)->where(fn ($q) => $q->where('client_id', $user->getKey())->orWhere('freelancer_id', $user->getKey()))->first(['id', 'client_id', 'freelancer_id', 'reference']);
            if ($order === null) {
                throw ValidationException::withMessages(['order' => 'Commande introuvable.']);        // même réponse : inexistante ou d'autrui
            }
        }
        $this->throttle($user);

        [$id, $replayed] = CommandReceipts::once($user->getKey(), 'support.open', $operationKey, ['s' => $subject, 'b' => $body, 'c' => $category, 'o' => $orderReference],
            function () use ($user, $subject, $body, $category, $order) {
                $id = $this->store->create([
                    'kind' => 'support', 'requester_id' => $user->getKey(), 'order_id' => $order?->id, 'target_type' => $order ? 'order' : null, 'target_id' => $order?->id, 'target_label' => $order?->reference,
                    'category' => $category, 'subject' => $subject,
                ]);
                $this->store->message($id, $user->getKey(), 'requester', $body);
                $this->store->event($id, 'opened', $user->getKey(), 'requester', 'Demande envoyée');

                return $id;
            });

        return [DB::table('support_cases')->where('id', $id)->value('reference'), $replayed];
    }

    /**
     * @return array{0: string, 1: bool}
     *
     * @throws SupportConflict
     */
    public function report(User $user, string $type, string $targetId, string $reason, string $body, string $operationKey): array
    {
        if (! isset(CaseRules::TARGETS[$type]) || ! isset(CaseRules::REPORT_REASONS[$reason])) {
            throw ValidationException::withMessages(['reason' => 'Choisissez un motif.']);
        }
        $body = trim($body);
        if (mb_strlen($body) < 10 || mb_strlen($body) > (int) config('freeci.support.body_max')) {
            throw ValidationException::withMessages(['body' => 'Décrivez le problème (10 à '.config('freeci.support.body_max').' caractères).']);
        }
        [$label, $snapshot, $targetId] = $this->target($user, $type, $targetId);
        $this->throttle($user);

        $dup = DB::table('support_cases')->where('kind', 'report')->where('requester_id', $user->getKey())->where('target_type', $type)->where('target_id', $targetId)->whereIn('status', CaseRules::LIVE)->value('reference');
        if ($dup !== null) {
            throw new SupportConflict("Vous avez déjà signalé ce contenu : suivez le dossier {$dup}.");
        }

        [$id, $replayed] = CommandReceipts::once($user->getKey(), 'support.report', $operationKey, ['t' => $type, 'i' => $targetId, 'r' => $reason, 'b' => $body],
            function () use ($user, $type, $targetId, $reason, $body, $label, $snapshot) {
                $id = $this->store->create([
                    'kind' => 'report', 'requester_id' => $user->getKey(), 'target_type' => $type, 'target_id' => $targetId, 'target_label' => $label, 'target_snapshot' => $snapshot,
                    'category' => $reason, 'subject' => mb_substr(CaseRules::TARGETS[$type].' signalé : '.CaseRules::REPORT_REASONS[$reason], 0, 160),
                ]);
                $this->store->message($id, $user->getKey(), 'requester', $body);
                $this->store->event($id, 'opened', $user->getKey(), 'requester', 'Signalement envoyé');

                return $id;
            });

        return [DB::table('support_cases')->where('id', $id)->value('reference'), $replayed];
    }

    /** @return array{0: string, 1: ?string, 2: string} [libellé, copie du message signalé, identifiant réel] */
    private function target(User $user, string $type, string $id): array
    {
        $uid = $user->getKey();
        $none = fn () => throw new SupportConflict('Ce contenu est introuvable.');
        switch ($type) {
            case 'profile':
                $p = DB::table('freelance_profiles')->where(Str::isUuid($id) ? 'id' : 'slug', $id)->whereNotNull('published_at')->first(['id', 'user_id', 'display_name']) ?? $none();
                $id = $p->id;
                $owner = $p->user_id;
                $label = $p->display_name;
                break;
            case 'service':
                $s = DB::table('services')->join('freelance_profiles as p', 'p.id', '=', 'services.freelance_profile_id')->where(Str::isUuid($id) ? 'services.id' : 'services.slug', $id)->where('services.status', 'published')->first(['services.id', 'p.user_id', 'services.title']) ?? $none();
                $id = $s->id;
                $owner = $s->user_id;
                $label = $s->title;
                break;
            case 'mission':
                $m = DB::table('missions')->where(Str::isUuid($id) ? 'id' : 'slug', $id)->whereIn('status', ['open', 'reserved', 'awarded', 'selection_ended', 'suspended'])->first(['id', 'client_id']) ?? $none();
                $id = $m->id;
                $owner = $m->client_id;
                $label = (string) DB::table('mission_versions')->where('mission_id', $id)->orderByDesc('number')->value('title');
                break;
            case 'review':
            case 'reply':
                // Seul un contenu PUBLIC (publié, non masqué, commande réelle) se signale ; l'identifiant est celui de l'avis (la réponse lui est rattachée).
                $rv = ReviewVisibility::published(DB::table('reviews'))->where('reviews.id', $id)->first(['reviews.id', 'reviews.author_id', 'reviews.subject_id', 'reviews.rating', 'reviews.comment']) ?? $none();
                $reply = $type === 'reply' ? (DB::table('review_responses')->where('review_id', $rv->id)->whereNull('hidden_at')->first() ?? $none()) : null;
                $id = $rv->id;
                $owner = $type === 'review' ? $rv->author_id : $reply->author_id;
                $label = $type === 'review' ? 'Avis ('.$rv->rating.'/5)' : 'Réponse à un avis';
                $snapshot = mb_substr($type === 'review' ? $rv->comment : $reply->body, 0, 2000);
                break;
            default:      // message : seulement un message d'une conversation dont on est participant, jamais le sien
                $row = DB::table('messages')->join('conversations as c', 'c.id', '=', 'messages.conversation_id')->where('messages.id', (int) $id)
                    ->where(fn ($q) => $q->where('c.client_id', $uid)->orWhere('c.freelancer_id', $uid))->first(['messages.sender_id', 'messages.body', 'c.context_title']) ?? $none();
                $owner = $row->sender_id;
                $label = 'Message dans « '.mb_substr($row->context_title, 0, 80).' »';
                $snapshot = mb_substr((string) $row->body, 0, 2000);
        }
        if ($owner === $uid) {
            throw new SupportConflict('Vous ne pouvez pas signaler votre propre contenu.');
        }

        return [mb_substr((string) $label, 0, 200), $snapshot ?? null, (string) $id];
    }

    /** @return array{0: string, 1: string} */
    private function text(string $subject, string $body): array
    {
        $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? '');
        $body = trim($body);
        $e = [];
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 160) {
            $e['subject'] = 'Indiquez un objet (3 à 160 caractères).';
        }
        if (mb_strlen($body) < 10 || mb_strlen($body) > (int) config('freeci.support.body_max')) {
            $e['body'] = 'Décrivez votre demande (10 à '.config('freeci.support.body_max').' caractères).';
        }
        if ($e !== []) {
            throw ValidationException::withMessages($e);
        }

        return [$subject, $body];
    }

    private function throttle(User $user): void
    {
        $n = DB::table('support_cases')->where('requester_id', $user->getKey())->whereIn('kind', ['support', 'report'])->where('opened_at', '>=', now()->subDay())->count();
        if ($n >= (int) config('freeci.support.per_day')) {
            throw new SupportConflict('Vous avez atteint la limite de dossiers ouverts sur 24 heures. Poursuivez dans un dossier existant ou réessayez demain.');
        }
    }
}
