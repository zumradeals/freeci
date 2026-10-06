<?php

namespace App\Modules\Support\Queries;

use App\Modules\Support\Contracts\PayoutExecution;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Support\Facades\DB;

/** Ce qu'une partie peut demander sur une commande, selon son état et selon que le reversement est exécuté ou non. Calculé côté serveur, revérifié à l'ouverture. */
final class DisputeOptions
{
    public function __construct(private PayoutExecution $payouts) {}

    /** @return array{kinds: list<string>, live: ?string, executed: bool} */
    public function for(string $orderId, string $state): array
    {
        $executed = $this->payouts->executed($orderId);
        $live = DB::table('support_cases')->where('order_id', $orderId)->whereIn('kind', ['dispute', 'cancellation'])->whereIn('status', CaseRules::LIVE)->value('reference');
        $kinds = [];
        if ($live === null) {
            if (! $executed && in_array($state, CaseRules::DISPUTE_STATES, true)) {
                $kinds[] = 'dispute';
            }
            if (! $executed && in_array($state, CaseRules::CANCEL_STATES, true)) {
                $kinds[] = 'cancellation';
            }
        }
        if ($executed) {
            $kinds[] = 'claim';
        }

        return ['kinds' => $kinds, 'live' => $live, 'executed' => $executed];
    }
}
