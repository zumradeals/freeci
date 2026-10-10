<?php

namespace App\Modules\Admin\Queries;

use App\Modules\Admin\Support\StatsPeriod;
use App\Shared\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Statistiques d'administration (F-17), en lecture seule. Réel et test ne sont JAMAIS mélangés : « réel » = commandes dont l'environnement est « live » ; « test » = toutes les autres
 * (test et anciennes). Pour les comptes et les publications, qui n'ont pas d'environnement, le réel exclut les comptes de démonstration et le test les inclut.
 * Chaque indicateur porte sa formule. Nombres entiers (francs) ; jamais de donnée privée.
 */
final class Statistics
{
    /** @return array<string, mixed> */
    public function __invoke(StatsPeriod $p, bool $live): array
    {
        $cur = $this->figures($p->from, $p->to, $live);
        [$pf, $pt] = $p->previous();
        $prev = $this->figures($pf, $pt, $live);
        $delta = function (string $k) use ($cur, $prev): ?string {
            if ($prev[$k] > 0) {
                $pc = (int) round(($cur[$k] - $prev[$k]) * 100 / $prev[$k]);

                return ($pc > 0 ? '+' : ($pc < 0 ? '−' : '')).abs($pc).' % vs période précédente';
            }

            return $cur[$k] > 0 ? 'Aucune activité sur la période précédente' : null;
        };
        $n = fn (int $v) => number_format($v, 0, ',', ' ');
        $xof = fn (int $v) => Money::xof($v)->formatted().' FCFA';
        $pct = fn (?int $v) => $v === null ? '—' : $v.' %';

        return [
            'activity' => [
                $this->card('Inscriptions', $n($cur['registrations']), $delta('registrations'), 'Comptes créés dans la période'),
                $this->card('Freelances publiés', $n($cur['freelancers']), $delta('freelancers'), 'Profils dont la publication date de la période'),
                $this->card('Services publiés', $n($cur['services']), $delta('services'), 'Services dont la première publication approuvée date de la période'),
                $this->card('Missions publiées', $n($cur['missions']), $delta('missions'), 'Missions dont la première publication approuvée date de la période'),
                $this->card('Commandes créées', $n($cur['orders']), $delta('orders'), 'Demandes de prestation, sélections de proposition et offres acceptées'),
                $this->card('Commandes clôturées', $n($cur['closed']), $delta('closed'), 'Commandes dont la livraison a été validée par le client, par date de clôture'),
                $this->card('Taux d’acceptation', $pct($cur['acceptance']), null, 'Demandes acceptées ÷ demandes répondues (acceptées + refusées), demandes de services de la période'),
                $this->card('Taux de paiement', $pct($cur['payRate']), null, 'Commandes payées ÷ commandes acceptées, acceptées dans la période'),
            ],
            'finance' => [
                $this->card('Encaissé', $xof($cur['collected']), $delta('collected'), 'Somme des paiements confirmés, par date de confirmation'),
                $this->card('Commissions', $xof($cur['commission']), $delta('commission'), 'Taux figé de chaque accord × prix de chaque commande clôturée (arrondi par commande)'),
                $this->card('Commissions offertes', $xof($cur['offered']), $delta('offered'), 'Parrainage et codes promotionnels : commission normale − commission appliquée, commandes clôturées de la période'),
                $this->card('Remboursements', $xof($cur['refunds']), $n($cur['refundCount']).' opération'.($cur['refundCount'] > 1 ? 's' : ''), 'Opérations de remboursement confirmées, par date de confirmation'),
                $this->card('Reversements', $xof($cur['payouts']), $n($cur['payoutCount']).' effectué'.($cur['payoutCount'] > 1 ? 's' : '').' · '.$n($cur['payoutOpen']).' en cours', 'Part du freelance confirmée avec preuve ; « en cours » : demandés, approuvés, en cours ou à vérifier'),
            ],
            'byOrigin' => $cur['byOrigin'],
            'quality' => [
                $this->card('Avis publiés', $n($cur['reviews']), null, 'Avis devenus visibles dans la période'),
                $this->card('Note moyenne', $cur['avg'] === null ? '—' : number_format($cur['avg'], 1, ',', '').' / 5', null, 'Moyenne des avis publiés dans la période'),
                $this->card('Litiges ouverts', $n($cur['disputes']), null, 'Dossiers de litige ouverts dans la période'),
                $this->card('Délais d’examen dépassés', $n($cur['overdue']), null, 'Livraisons restées sans réponse du client après le délai d’examen'),
            ],
            'series' => [
                'orders' => $this->series($p, fn (Builder $q) => $this->orders($q, $live)->selectRaw("(orders.created_at AT TIME ZONE 'Africa/Abidjan')::date as d, count(*) as v")->whereBetween('orders.created_at', [$p->from, $p->to->copy()->subSecond()])->groupBy('d')),
                'collected' => $this->series($p, fn (Builder $q) => $this->payments($q, $live)->selectRaw("(payments.confirmed_at AT TIME ZONE 'Africa/Abidjan')::date as d, sum(payments.amount_xof) as v")->where('payments.state', 'confirmed')->whereBetween('payments.confirmed_at', [$p->from, $p->to->copy()->subSecond()])->groupBy('d'), 'payments'),
            ],
            'period' => $p->label(), 'days' => $p->days(), 'live' => $live,
        ];
    }

    /** @return array{label: string, value: string, delta: ?string, formula: string} */
    private function card(string $label, string $value, ?string $delta, string $formula): array
    {
        return ['label' => $label, 'value' => $value, 'delta' => $delta, 'formula' => $formula];
    }

    private function orders(?Builder $q, bool $live): Builder
    {
        $q ??= DB::table('orders');
        $q->from('orders');

        return $live ? $q->where('orders.environment', 'live') : $q->where('orders.environment', '<>', 'live');
    }

    private function payments(?Builder $q, bool $live): Builder
    {
        $q ??= DB::table('payments');
        $q->from('payments')->join('orders', 'orders.id', '=', 'payments.order_id');

        return $live ? $q->where('orders.environment', 'live') : $q->where('orders.environment', '<>', 'live');
    }

    /** Comptes et publications : le réel exclut les comptes de démonstration. */
    private function accounts(Builder $q, string $table, bool $live): Builder
    {
        return $live ? $q->where("{$table}.is_demo", false) : $q;
    }

    /** @return array<string, mixed> */
    private function figures(Carbon $from, Carbon $to, bool $live): array
    {
        $range = fn (Builder $q, string $col) => $q->where($col, '>=', $from)->where($col, '<', $to);
        $count = fn (Builder $q) => (int) $q->count();

        $registrations = $count($range($this->accounts(DB::table('users'), 'users', $live), 'users.created_at'));
        $freelancers = $count($range($this->accounts(DB::table('freelance_profiles'), 'freelance_profiles', $live), 'freelance_profiles.published_at'));
        $services = $count($range($this->accounts(DB::table('services'), 'services', $live), 'services.published_at'));
        $missions = $count($range($this->accounts(DB::table('missions'), 'missions', $live), 'missions.published_at'));

        $orders = $count($range($this->orders(null, $live), 'orders.created_at'));
        $closedQ = fn () => $range($this->orders(null, $live)->where('orders.closure_reason', 'validated'), 'orders.closed_at');
        $closed = $count($closedQ());
        $commission = (int) $closedQ()->join('order_agreements as a', 'a.order_id', '=', 'orders.id')->sum(DB::raw('((a.price_xof * a.commission_bp + 5000) / 10000)'));

        $offered = (int) $closedQ()->join('order_agreements as a2', 'a2.order_id', '=', 'orders.id')->whereNotNull('a2.commission_base_bp')
            ->sum(DB::raw('(((a2.price_xof * a2.commission_base_bp + 5000) / 10000) - ((a2.price_xof * a2.commission_bp + 5000) / 10000))'));
        $svc = fn () => $range($this->orders(null, $live)->where('orders.origin', 'service'), 'orders.created_at');
        $accepted = $count($svc()->whereNotNull('orders.accepted_at'));
        $declined = $count($svc()->where('orders.closure_reason', 'declined'));
        $acceptance = $accepted + $declined > 0 ? (int) round($accepted * 100 / ($accepted + $declined)) : null;
        $acc = fn () => $range($this->orders(null, $live)->whereNotNull('orders.accepted_at'), 'orders.accepted_at');
        $acceptedAll = $count($acc());
        $paid = $count($acc()->whereExists(fn ($w) => $w->select(DB::raw(1))->from('payments')->whereColumn('payments.order_id', 'orders.id')->where('payments.state', 'confirmed')));
        $payRate = $acceptedAll > 0 ? (int) round($paid * 100 / $acceptedAll) : null;

        $collectedQ = fn () => $range($this->payments(null, $live)->where('payments.state', 'confirmed'), 'payments.confirmed_at');
        $collected = (int) $collectedQ()->sum('payments.amount_xof');
        $ops = fn (string $kind, array $states, string $col) => $range(DB::table('financial_operations')->join('orders', 'orders.id', '=', 'financial_operations.order_id')
            ->where('financial_operations.kind', $kind)->whereIn('financial_operations.state', $states)->tap(fn ($q) => $live ? $q->where('orders.environment', 'live') : $q->where('orders.environment', '<>', 'live')), $col);
        $refundQ = fn () => $ops('refund', ['confirmed'], 'financial_operations.confirmed_at');
        $payoutQ = fn () => $ops('payout', ['confirmed'], 'financial_operations.confirmed_at');
        $payoutOpen = (int) DB::table('financial_operations')->join('orders', 'orders.id', '=', 'financial_operations.order_id')->where('financial_operations.kind', 'payout')
            ->whereIn('financial_operations.state', ['requested', 'approved', 'in_progress', 'to_verify'])->tap(fn ($q) => $live ? $q->where('orders.environment', 'live') : $q->where('orders.environment', '<>', 'live'))->count();

        $labels = ['service' => 'Services', 'mission' => 'Missions (jalons compris)', 'offer' => 'Offres personnalisées'];
        $byOrigin = [];
        foreach ($labels as $origin => $label) {
            $byOrigin[] = [
                'label' => $label, 'orders' => $count($range($this->orders(null, $live)->where('orders.origin', $origin), 'orders.created_at')),
                'collected' => Money::xof((int) $range($this->payments(null, $live)->where('payments.state', 'confirmed')->where('orders.origin', $origin), 'payments.confirmed_at')->sum('payments.amount_xof'))->formatted().' FCFA',
            ];
        }

        $rev = fn () => $range(DB::table('reviews')->join('orders', 'orders.id', '=', 'reviews.order_id')->whereNull('reviews.hidden_at')->tap(fn ($q) => $live ? $q->where('orders.environment', 'live')->where('reviews.counts_public', true) : $q->where('orders.environment', '<>', 'live')), 'reviews.visible_at');
        $avg = $rev()->avg('reviews.rating');
        $disputes = $count($range(DB::table('support_cases')->join('orders', 'orders.id', '=', 'support_cases.order_id')->where('support_cases.kind', 'dispute')->tap(fn ($q) => $live ? $q->where('orders.environment', 'live') : $q->where('orders.environment', '<>', 'live')), 'support_cases.created_at'));
        $overdue = $count($range(DB::table('order_follow_ups')->join('orders', 'orders.id', '=', 'order_follow_ups.order_id')->where('order_follow_ups.kind', 'review_silence')->tap(fn ($q) => $live ? $q->where('orders.environment', 'live') : $q->where('orders.environment', '<>', 'live')), 'order_follow_ups.recorded_at'));

        return [
            'registrations' => $registrations, 'freelancers' => $freelancers, 'services' => $services, 'missions' => $missions, 'orders' => $orders, 'closed' => $closed,
            'commission' => $commission, 'offered' => $offered, 'acceptance' => $acceptance, 'payRate' => $payRate, 'collected' => $collected,
            'refunds' => (int) $refundQ()->sum('financial_operations.amount_xof'), 'refundCount' => $count($refundQ()),
            'payouts' => (int) $payoutQ()->sum('financial_operations.amount_xof'), 'payoutCount' => $count($payoutQ()), 'payoutOpen' => $payoutOpen,
            'byOrigin' => $byOrigin, 'reviews' => $count($rev()), 'avg' => $avg === null ? null : (float) $avg, 'disputes' => $disputes, 'overdue' => $overdue,
        ];
    }

    /**
     * Série quotidienne complète (jours sans activité à zéro) + tableau équivalent.
     *
     * @return array{points: list<array{day: string, value: int}>, max: int}
     */
    private function series(StatsPeriod $p, callable $build, string $base = 'orders'): array
    {
        $q = DB::table($base);
        $rows = $build($q)->pluck('v', 'd');
        $pts = [];
        for ($d = $p->from->copy(); $d->lt($p->to); $d->addDay()) {
            $pts[] = ['day' => $d->format('Y-m-d'), 'value' => (int) ($rows[$d->format('Y-m-d')] ?? 0)];
        }

        return ['points' => $pts, 'max' => max(1, max(array_column($pts, 'value') ?: [0]))];
    }
}
