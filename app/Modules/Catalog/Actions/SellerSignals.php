<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Support\Availability;
use App\Modules\Orders\Queries\ResponseStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ce que le public voit de la disponibilité et de la réactivité d'un freelance : indisponibilité (avec la date de retour si elle est annoncée), tranche de temps de réponse et part des demandes
 * traitées dans le délai — CALCULÉES, jamais déclarées, et seulement à partir de 5 demandes (sinon « nouveau »). Mémo par requête (service « scoped »), préchargeable pour une liste.
 */
final class SellerSignals
{
    /** @var array<string, array{available: bool, back_on: ?string, label: ?string, rate: ?int, is_new: bool, count: int}> */
    private array $memo = [];

    public function __construct(private ResponseStats $stats) {}

    /** Oublie le mémo (après un changement de disponibilité dans la même requête ou le même processus). */
    public function flush(): void
    {
        $this->memo = [];
    }

    /** @param  list<string>  $userIds */
    public function preload(array $userIds): void
    {
        $missing = array_values(array_diff(array_unique(array_filter($userIds)), array_keys($this->memo)));
        if ($missing === []) {
            return;
        }
        $profiles = DB::table('freelance_profiles')->whereIn('user_id', $missing)->get(['user_id', 'unavailable_at', 'back_on', 'auto_reopen'])->keyBy('user_id');
        $stats = $this->stats->forFreelancers($missing);
        foreach ($missing as $id) {
            $p = $profiles->get($id);
            $s = $stats[$id] ?? ['count' => 0, 'label' => null, 'rate' => null];
            $off = $p !== null && Availability::unavailable($p);
            $this->memo[$id] = [
                'available' => ! $off, 'back_on' => $off && $p->back_on !== null ? Carbon::parse($p->back_on)->translatedFormat('j F') : null,
                'label' => $s['label'], 'rate' => $s['rate'], 'is_new' => $s['count'] < ResponseStats::MIN, 'count' => $s['count'],
            ];
        }
    }

    /** @return array{available: bool, back_on: ?string, label: ?string, rate: ?int, is_new: bool, count: int} */
    public function for(string $userId): array
    {
        $this->preload([$userId]);

        return $this->memo[$userId] ?? ['available' => true, 'back_on' => null, 'label' => null, 'rate' => null, 'is_new' => true, 'count' => 0];
    }
}
