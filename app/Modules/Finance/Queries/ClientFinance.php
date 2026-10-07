<?php

namespace App\Modules\Finance\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Support\FinanceLabels;
use App\Modules\Finance\Support\OrderFunds;
use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** État financier des commandes d'un CLIENT : encaissé, remboursements (états réels, jamais « effectué » avant confirmation). Test et réel séparés. */
final class ClientFinance
{
    /** Totals cover the full history; the detail list is paginated independently. */
    public function overview(User $client, int $page = 1): array
    {
        $orders = DB::table('orders as o')->join('order_agreements as a', 'a.order_id', '=', 'o.id')->where('o.client_id', $client->getKey())
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments as p')->whereRaw('p.order_id = o.id')->where('p.state', 'confirmed'))
            ->select(['o.id', 'o.reference', 'o.environment', 'a.service_title'])
            ->orderByDesc('o.updated_at')->orderByDesc('o.id')->lazy(100);
        $zero = ['paid' => 0, 'refunded' => 0, 'refund_open' => 0];
        $totals = ['real' => $zero, 'test' => $zero, 'legacy' => $zero];
        $page = max(1, $page);
        $perPage = 20;
        $count = 0;
        $rows = [];
        foreach ($orders as $order) {
            $f = OrderFunds::summary($order->id);
            $bucket = match ($order->environment) {
                'live' => 'real',
                'test' => 'test',
                default => 'legacy',
            };
            foreach (array_keys($zero) as $key) {
                $totals[$bucket][$key] += $f[$key];
            }
            $index = $count++;
            if ($index >= ($page - 1) * $perPage && $index < $page * $perPage) {
                $rows[] = $this->forOrderRow($order, $f);
            }
        }

        return ['totals' => $totals, 'rows' => $rows, 'pagination' => new LengthAwarePaginator($rows, $count, $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()])];
    }

    /** @return array<string, mixed> */
    public function forOrder(string $orderId): array
    {
        $o = DB::table('orders as o')->join('order_agreements as a', 'a.order_id', '=', 'o.id')->where('o.id', $orderId)->first(['o.id', 'o.reference', 'o.environment', 'a.service_title']);

        return $this->forOrderRow($o);
    }

    /** @return array<string, mixed> */
    private function forOrderRow(object $o, ?array $summary = null): array
    {
        $f = $summary ?? OrderFunds::summary($o->id);
        $refunds = DB::table('financial_operations')->where('order_id', $o->id)->where('kind', 'refund')->orderBy('created_at')->get()->map(fn ($r) => [
            'reference' => $r->reference, 'amount' => (int) $r->amount_xof, 'state' => $r->state, 'label' => FinanceLabels::PARTY_STATES[$r->state], 'tone' => FinanceLabels::TONES[$r->state], 'simulated' => (bool) $r->is_simulated,
            'when' => Dates::format(Carbon::parse($r->confirmed_at ?? $r->created_at)),
        ])->all();

        return ['reference' => $o->reference, 'title' => $o->service_title, 'environment' => $o->environment, 'paid' => $f['paid'], 'refunded' => $f['refunded'], 'refund_open' => $f['refund_open'], 'simulated' => $f['simulated'], 'refunds' => $refunds];
    }
}
