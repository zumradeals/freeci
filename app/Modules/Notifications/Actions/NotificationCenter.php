<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Support\MailStatus;
use App\Modules\Notifications\Support\NotificationTypes;
use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

/** Centre de notifications : toujours borné à l'utilisateur connecté. Lien = route nommée + paramètres ; l'écran cible revérifie les droits. */
final class NotificationCenter
{
    public function unread(User $user): int
    {
        return AppNotification::query()->where('user_id', $user->getKey())->whereNull('read_at')->count();
    }

    public function page(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return AppNotification::query()->where('user_id', $user->getKey())->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage)->through(fn (AppNotification $n) => [
            'id' => $n->getKey(), 'title' => $n->title, 'body' => $n->body, 'when' => Dates::format($n->updated_at), 'unread' => $n->read_at === null, 'optional' => $n->category === NotificationTypes::OPTIONAL,
        ]);
    }

    /** Marque lue puis retourne l'URL cible (ou le centre si le lien n'est plus valide). Notification d'autrui : null (404). */
    public function open(User $user, int $id): ?string
    {
        $n = AppNotification::query()->whereKey($id)->where('user_id', $user->getKey())->first();
        if ($n === null) {
            return null;
        }
        if ($n->read_at === null) {
            $n->forceFill(['read_at' => now()])->save();
        }

        return Route::has($n->route) ? route($n->route, $n->route_params ?? []) : route('notifications.index');
    }

    public function markRead(User $user, int $id): bool
    {
        return AppNotification::query()->whereKey($id)->where('user_id', $user->getKey())->whereNull('read_at')->update(['read_at' => now()]) > 0;
    }

    public function markAllRead(User $user): int
    {
        return AppNotification::query()->where('user_id', $user->getKey())->whereNull('read_at')->update(['read_at' => now()]);
    }

    /**
     * Préférences : types facultatifs réglables, types indispensables listés mais non désactivables.
     *
     * @return array{optional: list<array<string, mixed>>, essential: list<array<string, string>>, mailAvailable: bool}
     */
    public function preferences(User $user): array
    {
        $prefs = NotificationPreference::query()->where('user_id', $user->getKey())->get()->keyBy('type');
        $optional = [];
        foreach (NotificationTypes::optional() as $key => $t) {
            $optional[] = ['key' => $key, 'label' => $t['label'], 'description' => $t['description'], 'in_app' => $prefs[$key]->in_app ?? true, 'email' => $prefs[$key]->email ?? false];
        }
        $essential = collect(NotificationTypes::all())->filter(fn ($t) => $t['category'] === NotificationTypes::ESSENTIAL)->map(fn ($t) => ['label' => $t['label'], 'description' => $t['description']])->values()->all();

        return ['optional' => $optional, 'essential' => $essential, 'mailAvailable' => MailStatus::deliverable()];
    }

    /** @param array<string, array{in_app?: mixed, email?: mixed}> $input */
    public function savePreferences(User $user, array $input): void
    {
        foreach (array_keys(NotificationTypes::optional()) as $key) {
            NotificationPreference::query()->updateOrCreate(['user_id' => $user->getKey(), 'type' => $key], [
                'in_app' => ! empty($input[$key]['in_app']), 'email' => ! empty($input[$key]['email']),
            ]);
        }
    }
}
