<?php

namespace App\Modules\Finance\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Support\FinanceLabels;
use App\Modules\Finance\Support\FinancialPolicy;
use App\Modules\Finance\Support\OrderFunds;
use App\Modules\Finance\Support\PayoutEligibility;
use App\Modules\Support\Support\CaseRules;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Lectures du tableau de bord financier (administrateurs). Montants entiers FCFA ; réel et test toujours séparés. */
final class FinanceAdmin
{
    /** @return array<string, mixed> */
    public function index(User $viewer): array
    {
        $row = fn ($o) => [
            'id' => $o->id, 'reference' => $o->reference, 'kind' => FinanceLabels::KINDS[$o->kind], 'kind_raw' => $o->kind, 'scope' => $o->scope, 'order' => $o->order_reference, 'amount' => (int) $o->amount_xof,
            'state' => $o->state, 'label' => FinanceLabels::state($o->state), 'tone' => FinanceLabels::TONES[$o->state], 'simulated' => (bool) $o->is_simulated, 'environment' => $o->environment,
            'mode' => $o->execution_mode, 'when' => Dates::format(Carbon::parse($o->created_at)), 'mine' => $o->requested_by === $viewer->getKey(), 'reason' => $o->uncertain_reason,
            'approvals' => (int) ($o->approvals ?? 0), 'required' => FinancialPolicy::requiredApprovals((int) $o->amount_xof),
        ];
        $base = fn () => DB::table('financial_operations as f')->join('orders as o', 'o.id', '=', 'f.order_id')
            ->selectRaw("f.*, o.reference as order_reference, (select count(*) from financial_operation_approvals a where a.operation_id = f.id and a.decision = 'approved') as approvals");

        $decisions = DB::table('support_decisions as d')->join('support_cases as c', 'c.id', '=', 'd.case_id')->join('orders as o', 'o.id', '=', 'c.order_id')
            ->where('d.financial_status', 'to_process')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('financial_operations as f')->whereRaw('f.support_decision_id = d.id')->whereIn('f.state', ['requested', 'approved', 'in_progress', 'to_verify', 'confirmed']))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('financial_operations as f')->whereRaw('f.order_id = c.order_id')->where('f.kind', 'payout')->whereIn('f.state', ['requested', 'approved', 'in_progress', 'to_verify', 'confirmed'])->whereRaw("d.financial_need = 'release'"))
            ->orderBy('d.created_at')->limit(50)->get(['d.id', 'd.financial_need', 'd.financial_note', 'c.reference as case_reference', 'o.reference as order_reference', 'o.id as order_id']);

        return [
            'toApprove' => $base()->where('f.state', 'requested')->orderBy('f.created_at')->get()->map($row)->all(),
            'open' => $base()->whereIn('f.state', ['approved', 'in_progress', 'to_verify'])->orderBy('f.created_at')->get()->map($row)->all(),
            'history' => $base()->whereIn('f.state', ['confirmed', 'failed', 'rejected', 'cancelled'])->orderByDesc('f.created_at')->limit(50)->get()->map($row)->all(),
            'decisions' => $decisions->map(function ($d) {
                $p = OrderFunds::confirmedPayment($d->order_id);
                $cap = $p === null || $p->provider !== 'genius_pay' ? 0 : OrderFunds::escrow($d->order_id, (bool) $p->is_simulated);

                return ['id' => $d->id, 'case' => $d->case_reference, 'order' => $d->order_reference, 'need' => $d->financial_need, 'need_label' => CaseRules::FINANCIAL[$d->financial_need] ?? $d->financial_need, 'note' => $d->financial_note,
                    'cap' => $cap, 'paid' => $p === null ? 0 : (int) $p->amount_xof, 'simulated' => $p === null ? null : (bool) $p->is_simulated];
            })->all(),
            'eligible' => $this->payoutCandidates(),
            'beneficiaries' => DB::table('payout_beneficiaries as b')->join('users as u', 'u.id', '=', 'b.user_id')->where('b.status', 'pending')->orderBy('b.created_at')
                ->get(['b.id', 'b.method', 'b.holder_name', 'u.name', 'b.created_at'])->map(fn ($b) => ['id' => $b->id, 'method' => $b->method, 'holder' => $b->holder_name, 'user' => $b->name, 'when' => Dates::format(Carbon::parse($b->created_at))])->all(),
            'totals' => $this->totals(),
        ];
    }

    /** @return list<array<string, mixed>> commandes validées sans reversement : éligibles ET non éligibles (avec les motifs) */
    private function payoutCandidates(): array
    {
        $orders = DB::table('orders as o')->where(fn ($q) => $q->where('o.state', 'validated')->orWhere('o.closure_reason', 'validated'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments as p')->whereRaw('p.order_id = o.id')->where('p.state', 'confirmed'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('financial_operations as f')->whereRaw('f.order_id = o.id')->where('f.kind', 'payout')->whereIn('f.state', ['requested', 'approved', 'in_progress', 'to_verify', 'confirmed']))
            ->orderByDesc('o.updated_at')->limit(50)->get();

        return $orders->map(function ($o) {
            $e = PayoutEligibility::evaluate($o);

            return ['order' => $o->reference, 'eligible' => $e['eligible'], 'reasons' => array_map(fn ($r) => PayoutEligibility::REASONS[$r], $e['reasons']), 'base' => $e['base'], 'commission' => $e['commission'], 'due' => $e['due'], 'bp' => $e['bp'], 'simulated' => $e['simulated']];
        })->all();
    }

    /** @return array{real: array<string, int>, test: array<string, int>} */
    public function totals(): array
    {
        $sum = fn (bool $sim) => [
            'paid' => (int) DB::table('payments')->where('state', 'confirmed')->where('is_simulated', $sim)->sum('amount_xof'),
            'refunded' => (int) DB::table('financial_operations')->where('kind', 'refund')->where('state', 'confirmed')->where('is_simulated', $sim)->sum('amount_xof'),
            'refund_open' => (int) DB::table('financial_operations')->where('kind', 'refund')->whereIn('state', ['requested', 'approved', 'in_progress', 'to_verify'])->where('is_simulated', $sim)->sum('amount_xof'),
            'paid_out' => (int) DB::table('financial_operations')->where('kind', 'payout')->where('state', 'confirmed')->where('is_simulated', $sim)->sum('amount_xof'),
            'payout_open' => (int) DB::table('financial_operations')->where('kind', 'payout')->whereIn('state', ['requested', 'approved', 'in_progress', 'to_verify'])->where('is_simulated', $sim)->sum('amount_xof'),
            // Commission ACQUISE = commission des reversements CONFIRMÉS (jamais celle d'une réservation).
            'commission' => (int) DB::table('financial_operations')->where('kind', 'payout')->where('state', 'confirmed')->where('is_simulated', $sim)->sum('commission_xof'),
            'provider_fees' => (int) DB::table('payments')->where('state', 'confirmed')->where('is_simulated', $sim)->sum('provider_fee_xof'),
        ];

        return ['real' => $sum(false), 'test' => $sum(true)];
    }

    /** @return array<string, mixed>|null */
    public function detail(User $viewer, string $reference): ?array
    {
        $op = DB::table('financial_operations as f')->join('orders as o', 'o.id', '=', 'f.order_id')->where('f.reference', $reference)
            ->first(['f.*', 'o.reference as order_reference', 'o.client_id', 'o.freelancer_id', 'o.environment as order_environment', 'o.state as order_state']);
        if ($op === null) {
            return null;
        }
        $names = DB::table('users')->whereIn('id', array_filter([$op->requested_by, $op->executed_by, $op->client_id, $op->freelancer_id]))->pluck('name', 'id');
        $approvals = DB::table('financial_operation_approvals')->where('operation_id', $op->id)->orderBy('id')->get()
            ->map(fn ($a) => ['who' => DB::table('users')->where('id', $a->approver_id)->value('name'), 'decision' => $a->decision, 'note' => $a->note, 'when' => Dates::format(Carbon::parse($a->created_at))])->all();
        $events = DB::table('financial_operation_events')->where('operation_id', $op->id)->orderBy('id')->get()
            ->map(fn ($e) => ['type' => $e->type, 'note' => $e->note, 'when' => Dates::format(Carbon::parse($e->created_at)), 'who' => $e->actor_id ? DB::table('users')->where('id', $e->actor_id)->value('name') : 'Système'])->all();
        $batches = DB::table('ledger_batches')->where('operation_id', $op->id)->orderBy('occurred_at')->orderBy('event_key')->get()->map(fn ($b) => [
            'kind' => $b->kind, 'memo' => $b->memo, 'corrects' => $b->reverses_batch_id !== null, 'when' => Dates::format(Carbon::parse($b->occurred_at)),
            'lines' => DB::table('ledger_lines')->where('batch_id', $b->id)->orderBy('id')->get(['account', 'amount_xof'])->map(fn ($l) => ['account' => $l->account, 'amount' => (int) $l->amount_xof])->all(),
        ])->all();
        $isParty = in_array($viewer->getKey(), [$op->client_id, $op->freelancer_id], true);
        $approvedBy = collect($approvals)->count();
        $already = DB::table('financial_operation_approvals')->where('operation_id', $op->id)->where('approver_id', $viewer->getKey())->exists();
        $required = FinancialPolicy::requiredApprovals((int) $op->amount_xof);
        $beneficiary = $op->beneficiary_id === null ? null : DB::table('payout_beneficiaries')->where('id', $op->beneficiary_id)->first(['method', 'holder_name', 'status']);

        return [
            'op' => $op, 'label' => FinanceLabels::state($op->state), 'tone' => FinanceLabels::TONES[$op->state], 'kind' => FinanceLabels::KINDS[$op->kind],
            'requester' => $names[$op->requested_by] ?? '—', 'executor' => $op->executed_by ? ($names[$op->executed_by] ?? '—') : null, 'client' => $names[$op->client_id] ?? '—', 'freelancer' => $names[$op->freelancer_id] ?? '—',
            'approvals' => $approvals, 'required' => $required, 'events' => $events, 'batches' => $batches, 'beneficiary' => $beneficiary, 'simulated' => (bool) $op->is_simulated,
            'canApprove' => $op->state === 'requested' && ! $isParty && $op->requested_by !== $viewer->getKey() && ! $already, 'canCancel' => in_array($op->state, ['requested', 'approved'], true) && ! $isParty,
            'canExecuteApi' => $op->kind === 'refund' && $op->state === 'approved' && $op->scope === 'total' && ! $isParty,
            'canRecord' => $op->state === 'approved' && ! $isParty && ($op->kind === 'payout' || $op->scope === 'partial' || $op->execution_mode !== 'api'),
            'canVerify' => $op->state === 'to_verify' && $op->kind === 'refund' && ! $isParty, 'isParty' => $isParty, 'alreadyDecided' => $already, 'approved' => $approvedBy,
        ];
    }
}
