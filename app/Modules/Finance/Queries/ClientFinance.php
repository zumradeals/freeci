<?php

namespace App\Modules\Finance\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Support\FinanceLabels;
use App\Modules\Finance\Support\OrderFunds;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** État financier des commandes d'un CLIENT : encaissé, remboursements (états réels, jamais « effectué » avant confirmation). Test et réel séparés. */
final class ClientFinance
{
    /** @return list<array<string, mixed>> */
    public function orders(User $client): array
    {
        return DB::table('orders as o')->join('order_agreements as a', 'a.order_id', '=', 'o.id')->where('o.client_id', $client->getKey())
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments as p')->whereRaw('p.order_id = o.id')->whereIn('p.state', ['confirmed']))
            ->orderByDesc('o.updated_at')->limit(100)->get(['o.id', 'o.reference', 'o.environment', 'a.service_title'])->map(fn ($o) => $this->forOrderRow($o))->all();
    }

    /** @return array<string, mixed> */
    public function forOrder(string $orderId): array
    {
        $o = DB::table('orders as o')->join('order_agreements as a', 'a.order_id', '=', 'o.id')->where('o.id', $orderId)->first(['o.id', 'o.reference', 'o.environment', 'a.service_title']);

        return $this->forOrderRow($o);
    }

    /** @return array<string, mixed> */
    private function forOrderRow(object $o): array
    {
        $f = OrderFunds::summary($o->id);
        $refunds = DB::table('financial_operations')->where('order_id', $o->id)->where('kind', 'refund')->orderBy('created_at')->get()->map(fn ($r) => [
            'reference' => $r->reference, 'amount' => (int) $r->amount_xof, 'state' => $r->state, 'label' => FinanceLabels::PARTY_STATES[$r->state], 'tone' => FinanceLabels::TONES[$r->state], 'simulated' => (bool) $r->is_simulated,
            'when' => Dates::format(Carbon::parse($r->confirmed_at ?? $r->created_at)),
        ])->all();

        return ['reference' => $o->reference, 'title' => $o->service_title, 'environment' => $o->environment, 'paid' => $f['paid'], 'refunded' => $f['refunded'], 'refund_open' => $f['refund_open'], 'simulated' => $f['simulated'], 'refunds' => $refunds];
    }
}
