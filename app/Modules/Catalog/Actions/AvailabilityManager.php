<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Support\Availability;
use App\Modules\Notifications\Actions\Notify;
use App\Modules\Orders\Queries\ResponseStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Disponibilité du freelance (F-10) : c'est SON choix, jamais celui de l'administration. Indisponible = plus de NOUVELLE demande de prestation ; services visibles, commandes en cours et demandes
 * déjà reçues inchangées, propositions aux missions toujours possibles. Date de retour facultative (demain à un an) ; avec le retour automatique, la disponibilité revient ce jour-là.
 */
final class AvailabilityManager
{
    public function __construct(private Notify $notify) {}

    /** @return array{has_profile: bool, unavailable: bool, back_on: ?string, back_label: ?string, auto_reopen: bool, stats: array{count: int, label: ?string, rate: ?int, median_minutes: ?int}} */
    public function state(User $user): array
    {
        return $this->stateFor((string) $user->getKey());
    }

    /** @return array{has_profile: bool, unavailable: bool, back_on: ?string, back_label: ?string, auto_reopen: bool, stats: array{count: int, label: ?string, rate: ?int, median_minutes: ?int}} */
    public function stateFor(string $userId): array
    {
        $p = DB::table('freelance_profiles')->where('user_id', $userId)->first(['unavailable_at', 'back_on', 'auto_reopen']);
        $off = $p !== null && Availability::unavailable($p);

        return [
            'has_profile' => $p !== null, 'unavailable' => $off, 'back_on' => $off ? $p->back_on : null,
            'back_label' => $off && $p->back_on !== null ? Carbon::parse($p->back_on)->translatedFormat('j F Y') : null,
            'auto_reopen' => $off && (bool) $p->auto_reopen, 'stats' => app(ResponseStats::class)->forFreelancers([$userId])[$userId],
        ];
    }

    public function set(User $user, bool $unavailable, ?string $backOn, bool $autoReopen): void
    {
        $profile = FreelanceProfile::query()->where('user_id', $user->getKey())->first();
        if ($profile === null) {
            throw ValidationException::withMessages(['available' => 'Créez d’abord votre profil freelance.']);
        }
        if (! $unavailable) {
            $profile->forceFill(['unavailable_at' => null, 'back_on' => null, 'auto_reopen' => false])->save();
            app(SellerSignals::class)->flush();

            return;
        }
        $date = null;
        if ($backOn !== null && trim($backOn) !== '') {
            $date = date_create_immutable_from_format('!Y-m-d', trim($backOn));
            if ($date === false || $date->format('Y-m-d') !== trim($backOn)) {
                throw ValidationException::withMessages(['back_on' => 'Date de retour invalide.']);
            }
            if ($date <= now()->startOfDay() || $date > now()->addYear()) {
                throw ValidationException::withMessages(['back_on' => 'La date de retour doit être comprise entre demain et dans un an.']);
            }
        }
        $profile->forceFill([
            'unavailable_at' => $profile->unavailable_at !== null && Availability::unavailable($profile) ? $profile->unavailable_at : now(),
            'back_on' => $date?->format('Y-m-d'), 'auto_reopen' => $date !== null && $autoReopen,
        ])->save();
        app(SellerSignals::class)->flush();
    }

    /**
     * Constate les retours automatiques échus : remet le profil à « disponible » et prévient le freelance. Idempotent (une ligne n'est traitée qu'une fois).
     *
     * @return int nombre de profils rouverts
     */
    public function reopenDue(): int
    {
        $due = DB::table('freelance_profiles')->whereNotNull('unavailable_at')->where('auto_reopen', true)->whereNotNull('back_on')->whereRaw('back_on <= CURRENT_DATE')->get(['id', 'user_id', 'back_on']);
        $n = 0;
        foreach ($due as $p) {
            $done = DB::table('freelance_profiles')->where('id', $p->id)->whereNotNull('unavailable_at')->update(['unavailable_at' => null, 'back_on' => null, 'auto_reopen' => false, 'updated_at' => now()]);
            if ($done === 1) {
                ($this->notify)($p->user_id, 'availability_reopened', 'availability_reopened:'.$p->id.':'.$p->back_on, 'Vous êtes de nouveau disponible', 'Votre date de retour est arrivée : les clients peuvent de nouveau vous envoyer des demandes.', 'freelance.availability');
                $n++;
            }
        }

        app(SellerSignals::class)->flush();

        return $n;
    }
}
