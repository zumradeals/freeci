<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Jobs\SendNotificationEmail;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Support\MailStatus;
use App\Modules\Notifications\Support\NotificationTypes;
use Illuminate\Support\Facades\DB;

/**
 * Crée une notification dans l'application (et planifie son courriel). Idempotent : la clé `dedupe_key` identifie l'événement métier
 * (une ligne d'historique, une version…) ; le répéter ne crée rien. Les préférences ne s'appliquent qu'aux types facultatifs.
 */
final class Notify
{
    /**
     * @param  array<string, mixed>  $params
     * @return bool vrai si une nouvelle notification a été créée
     */
    public function __invoke(string $userId, string $type, string $dedupeKey, string $title, ?string $body, string $route, array $params = []): bool
    {
        $category = NotificationTypes::category($type);
        $pref = $category === NotificationTypes::OPTIONAL ? NotificationPreference::query()->where('user_id', $userId)->where('type', $type)->first() : null;
        $inApp = $pref?->in_app ?? true;
        $wantsEmail = $category === NotificationTypes::ESSENTIAL ? true : ($pref?->email ?? false);
        if (! $inApp) {
            return false;
        }

        $emailState = ! $wantsEmail ? 'none' : (MailStatus::deliverable() ? 'pending' : 'unavailable');
        $inserted = DB::table('app_notifications')->insertOrIgnore([
            'user_id' => $userId, 'type' => $type, 'category' => $category, 'dedupe_key' => $dedupeKey, 'title' => mb_substr($title, 0, 200), 'body' => $body === null ? null : mb_substr($body, 0, 400),
            'route' => $route, 'route_params' => json_encode($params), 'email_state' => $emailState, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($inserted === 1 && $emailState === 'pending') {
            $id = (int) DB::table('app_notifications')->where('user_id', $userId)->where('dedupe_key', $dedupeKey)->value('id');
            SendNotificationEmail::dispatch($id)->afterCommit();
        }

        return $inserted === 1;
    }

    /**
     * Nouveau message : regroupe les messages non lus d'une même conversation en UNE notification (pas de courriel supplémentaire).
     * Le texte du message n'est jamais repris.
     */
    public function message(string $recipientId, string $conversationId, int $messageId, string $senderName): void
    {
        $existing = AppNotification::query()->where('user_id', $recipientId)->where('type', 'message_received')->whereNull('read_at')
            ->where('route_params->conversation', $conversationId)->first();
        if ($existing !== null) {
            $n = $existing->count + 1;
            $existing->forceFill(['count' => $n, 'title' => "{$n} nouveaux messages de {$senderName}", 'updated_at' => now()])->save();

            return;
        }
        ($this)($recipientId, 'message_received', 'message:'.$conversationId.':'.$messageId, "Nouveau message de {$senderName}", null, 'messages.show', ['conversation' => $conversationId]);
    }
}
