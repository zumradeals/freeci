<?php

namespace App\Modules\Admin\Queries;

use App\Integrations\Payments\PaymentMode;
use App\Modules\Support\Queries\StaffQueue;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Compteurs RÉELS (aucune valeur factice). Les besoins de suivi sont de simples enregistrements : aucun litige n'est pris en charge à ce stade. */
final class AdminDashboard
{
    public function __invoke(): array
    {
        $kinds = ['review_silence' => 'Silence du client après le délai d’examen', 'client_disagreement' => 'Désaccord signalé après épuisement des corrections'];
        $followUps = DB::table('order_follow_ups')->join('orders', 'orders.id', '=', 'order_follow_ups.order_id')->orderByDesc('order_follow_ups.recorded_at')->limit(8)
            ->get(['orders.reference', 'order_follow_ups.kind', 'order_follow_ups.recorded_at']);

        return [
            'servicesInReview' => DB::table('service_versions')->where('state', 'in_review')->count(),
            'missionsInReview' => DB::table('mission_versions')->where('state', 'in_review')->count(),
            'users' => DB::table('users')->count(),
            'suspended' => DB::table('users')->whereNotNull('suspended_at')->count(),
            'unverified' => DB::table('users')->whereNull('email_verified_at')->count(),
            'followUps' => DB::table('order_follow_ups')->count(),
            'followUpList' => $followUps->map(fn ($r) => ['reference' => $r->reference, 'kind' => $kinds[$r->kind] ?? $r->kind, 'when' => Dates::format(Carbon::parse($r->recorded_at))])->all(),
            'securityAlerts' => DB::table('security_events')->whereIn('type', ['mfa_failed', 'mfa_locked', 'login_failed', 'login_locked', 'admin_denied', 'reauth_failed'])->where('created_at', '>=', now()->subDay())->count(),
            'paymentsToReview' => (new ReconciliationList)->counts(),
            // Séparation test / réel : les totaux réels ne comptent QUE des paiements live confirmés ; le test est présenté à part.
            'paymentMode' => ['live' => PaymentMode::isLive(), 'open' => PaymentMode::creationOpen(), 'message' => PaymentMode::adminMessage(PaymentMode::blocker())],
            'ordersByEnv' => DB::table('orders')->selectRaw('environment, count(*) c')->groupBy('environment')->pluck('c', 'environment')->all(),
            'confirmedLiveXof' => (int) DB::table('payments')->where('state', 'confirmed')->where('environment', 'live')->sum('amount_xof'),
            'confirmedTestXof' => (int) DB::table('payments')->where('state', 'confirmed')->where('environment', '<>', 'live')->sum('amount_xof'),
            'cases' => app(StaffQueue::class)->counts(auth()->user()),
            'recent' => (new AuditLog)->recent(6),
        ];
    }
}
