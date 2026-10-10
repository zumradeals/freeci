<?php

namespace App\Modules\Missions\Actions;

use App\Integrations\Payments\PaymentMode;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\PaymentGate;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Notifications\Actions\Notify;
use App\Modules\Orders\Actions\CancelBeforePayment;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Plan de jalons d'une mission (F-13). Chaque jalon est une commande ORDINAIRE : accord figé, paiement, livraison, litige et reversement ne changent pas.
 * Le plan se contente de les enchaîner : la commande du jalon suivant n'est créée qu'à la validation du précédent (le client ne paie qu'un jalon à la fois).
 * Plan figé à la sélection. États : active, paused (jalon non payé à temps), stopped, completed, discarded (le tout premier jalon n'a jamais abouti).
 */
final class MissionPlans
{
    public function __construct(private Notify $notify) {}

    /**
     * À appeler DANS la transaction de sélection, avant la création de la commande du jalon 1.
     *
     * @return array{plan: string, first: object, count: int}
     */
    public function create(string $missionId, object $proposalVersion, string $clientId, string $freelancerId): array
    {
        $ms = (array) $proposalVersion->milestones;
        $planId = (string) Str::uuid();
        DB::table('mission_plans')->insert(['id' => $planId, 'mission_id' => $missionId, 'proposal_version_id' => $proposalVersion->id, 'client_id' => $clientId, 'freelancer_id' => $freelancerId,
            'state' => 'active', 'total_xof' => array_sum(array_map(fn ($m) => (int) $m['price_xof'], $ms)), 'created_at' => now(), 'updated_at' => now()]);
        $first = null;
        foreach (array_values($ms) as $i => $m) {
            $id = (string) Str::uuid();
            DB::table('mission_plan_items')->insert(['id' => $id, 'plan_id' => $planId, 'rank' => $i + 1, 'title' => $m['title'], 'scope' => $m['scope'], 'price_xof' => (int) $m['price_xof'],
                'delivery_days' => (int) $m['days'], 'state' => 'upcoming', 'created_at' => now(), 'updated_at' => now()]);
            $first ??= DB::table('mission_plan_items')->where('id', $id)->first();
        }

        return ['plan' => $planId, 'first' => $first, 'count' => count($ms)];
    }

    /**
     * Champs de l'accord propres à un jalon (le reste est celui de la sélection).
     *
     * @return array<string, mixed>
     */
    public function agreementFor(object $item, int $count, string $missionTitle): array
    {
        return ['service_title' => $item->title, 'service_summary' => mb_substr("Jalon {$item->rank} sur {$count} de la mission « {$missionTitle} »", 0, 300), 'scope' => $item->scope,
            'price_xof' => (int) $item->price_xof, 'delivery_days' => (int) $item->delivery_days, 'deliverables' => [$item->title]];
    }

    /** Marque le jalon 1 « ouvert » une fois sa commande créée (même transaction que la sélection). */
    public function attachFirst(object $item, string $orderId): void
    {
        DB::table('mission_plan_items')->where('id', $item->id)->update(['state' => 'open', 'order_id' => $orderId, 'updated_at' => now()]);
    }

    /** Validation du jalon d'une commande (par le client ou par décision du support) : ouvre le suivant ou termine le plan. À appeler dans la transaction de validation. */
    public function onValidated(string $orderId): void
    {
        $o = DB::table('orders')->where('id', $orderId)->first(['id', 'milestone_item_id']);
        if ($o === null || $o->milestone_item_id === null) {
            return;
        }
        $item = DB::table('mission_plan_items')->where('id', $o->milestone_item_id)->lockForUpdate()->first();
        $plan = DB::table('mission_plans')->where('id', $item->plan_id)->lockForUpdate()->first();
        if ($item->state === 'validated' || in_array($plan->state, ['stopped', 'completed', 'discarded'], true)) {
            return;
        }
        DB::table('mission_plan_items')->where('id', $item->id)->update(['state' => 'validated', 'updated_at' => now()]);
        $next = DB::table('mission_plan_items')->where('plan_id', $plan->id)->where('rank', '>', $item->rank)->orderBy('rank')->first();
        $title = (string) DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $plan->mission_id)->value('v.title');
        if ($next === null) {
            DB::table('mission_plans')->where('id', $plan->id)->update(['state' => 'completed', 'paused_at' => null, 'ended_at' => now(), 'updated_at' => now()]);
            foreach ([$plan->client_id, $plan->freelancer_id] as $u) {
                ($this->notify)((string) $u, 'milestone_update', 'plan_completed:'.$plan->id.':'.$u, 'Plan de jalons terminé', "Tous les jalons de « {$title} » sont validés.", 'milestones.show', ['mission' => $plan->mission_id]);
            }

            return;
        }
        $order = $this->openOrder($plan, $next);
        ($this->notify)((string) $plan->client_id, 'milestone_opened', 'milestone_opened:'.$next->id.':'.$order->getKey(), "Jalon {$next->rank} ouvert", "Le jalon {$item->rank} est validé. Payez le jalon {$next->rank} « {$next->title} » pour lancer le travail.", 'orders.show', ['reference' => $order->reference]);
        ($this->notify)((string) $plan->freelancer_id, 'milestone_update', 'milestone_opened_f:'.$next->id.':'.$order->getKey(), "Jalon {$next->rank} ouvert", "Le jalon {$next->rank} « {$next->title} » attend le paiement du client.", 'orders.show', ['reference' => $order->reference]);
    }

    /** Une commande de jalon prend fin sans validation (expirée, annulée) : plan jeté (jalon 1 jamais abouti), en pause (paiement non fait) ou arrêté (annulation après paiement). */
    public function onOrderEnded(string $orderId): void
    {
        $o = DB::table('orders')->where('id', $orderId)->first(['id', 'milestone_item_id', 'closure_reason']);
        if ($o === null || $o->milestone_item_id === null) {
            return;
        }
        $item = DB::table('mission_plan_items')->where('id', $o->milestone_item_id)->lockForUpdate()->first();
        $plan = DB::table('mission_plans')->where('id', $item->plan_id)->lockForUpdate()->first();
        if ($plan->state !== 'active' || $item->order_id !== $o->id) {
            return;
        }
        $validated = DB::table('mission_plan_items')->where('plan_id', $plan->id)->where('state', 'validated')->count();
        if ($validated === 0) {
            DB::table('mission_plans')->where('id', $plan->id)->update(['state' => 'discarded', 'ended_at' => now(), 'updated_at' => now()]);
            DB::table('mission_plan_items')->where('plan_id', $plan->id)->whereIn('state', ['upcoming', 'open'])->update(['state' => 'cancelled', 'updated_at' => now()]);

            return;
        }
        $title = (string) DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $plan->mission_id)->value('v.title');
        $reason = $o->closure_reason;
        if (in_array($reason, ['expired_payment', 'cancelled_before_payment'], true)) {
            DB::table('mission_plans')->where('id', $plan->id)->update(['state' => 'paused', 'paused_at' => now(), 'updated_at' => now()]);
            $days = (int) config('freeci.missions.milestones.reopen_days');
            ($this->notify)((string) $plan->client_id, 'milestone_update', 'plan_paused:'.$plan->id.':'.$item->id.':c', 'Plan de jalons en pause', "Le jalon {$item->rank} de « {$title} » n’a pas été payé à temps. Vous pouvez rouvrir son paiement pendant {$days} jours.", 'milestones.show', ['mission' => $plan->mission_id]);
            ($this->notify)((string) $plan->freelancer_id, 'milestone_update', 'plan_paused:'.$plan->id.':'.$item->id.':f', 'Plan de jalons en pause', "Le jalon {$item->rank} de « {$title} » n’a pas été payé à temps : le client peut rouvrir son paiement pendant {$days} jours.", 'milestones.show', ['mission' => $plan->mission_id]);

            return;
        }
        $this->endPlan($plan, 'stopped', 'support', $title, 'Le jalon '.$item->rank.' a été annulé par décision du support : le plan est arrêté.');
    }

    /** Le client rouvre le paiement du jalon resté sans commande vivante (plan en pause, dans le délai). */
    public function reopen(User $client, string $missionId): string
    {
        return DB::transaction(function () use ($client, $missionId) {
            $plan = DB::table('mission_plans')->where('mission_id', $missionId)->where('client_id', $client->getKey())->where('state', '<>', 'discarded')->lockForUpdate()->first() ?? throw new MissionForbidden;
            if ($plan->state !== 'paused') {
                throw new MissionConflict('Ce plan n’est pas en pause : il n’y a rien à rouvrir.');
            }
            if (now()->gt(Carbon::parse($plan->paused_at)->addDays((int) config('freeci.missions.milestones.reopen_days')))) {
                throw new MissionConflict('Le délai de reprise est dépassé : le plan va être arrêté.');
            }
            $item = DB::table('mission_plan_items')->where('plan_id', $plan->id)->where('state', 'open')->lockForUpdate()->first() ?? throw new MissionConflict('Aucun jalon à rouvrir.');
            $order = $this->openOrder($plan, $item);
            DB::table('mission_plans')->where('id', $plan->id)->update(['state' => 'active', 'paused_at' => null, 'updated_at' => now()]);
            ($this->notify)((string) $plan->freelancer_id, 'milestone_update', 'plan_resumed:'.$plan->id.':'.$order->getKey(), 'Plan de jalons repris', "Le client a rouvert le paiement du jalon {$item->rank} « {$item->title} ».", 'milestones.show', ['mission' => $plan->mission_id]);

            return $order->reference;
        });
    }

    /** Arrêt du plan par le client, après au moins un jalon validé et sans jalon payé en cours. Les jalons non ouverts ne sont jamais dus. */
    public function stop(User $client, string $missionId): void
    {
        DB::transaction(function () use ($client, $missionId) {
            $plan = DB::table('mission_plans')->where('mission_id', $missionId)->where('client_id', $client->getKey())->where('state', '<>', 'discarded')->lockForUpdate()->first() ?? throw new MissionForbidden;
            if (! in_array($plan->state, ['active', 'paused'], true)) {
                throw new MissionConflict('Ce plan est déjà terminé ou arrêté.');
            }
            if (DB::table('mission_plan_items')->where('plan_id', $plan->id)->where('state', 'validated')->count() < 1) {
                throw new MissionConflict('Le plan ne peut être arrêté qu’après la validation d’au moins un jalon.');
            }
            $item = DB::table('mission_plan_items')->where('plan_id', $plan->id)->where('state', 'open')->lockForUpdate()->first();
            $order = $item?->order_id ? Order::query()->whereKey($item->order_id)->first() : null;
            $title = (string) DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $plan->mission_id)->value('v.title');
            $live = $order !== null && ! $order->state->isFinal();
            if ($live && $order->state !== OrderState::AwaitingPayment) {
                throw new MissionConflict('Un jalon est en cours de réalisation : il doit être mené à son terme (ou contesté auprès du support) avant d’arrêter le plan.');
            }
            $this->endPlan($plan, 'stopped', 'client', $title, 'Le client a arrêté le plan : les jalons restants sont annulés sans paiement.');
            if ($live) {      // jalon ouvert mais non payé : annulé par la voie ordinaire (refusée si un paiement est en cours) ; le plan étant déjà arrêté, le rappel est sans effet
                app(CancelBeforePayment::class)($client, $order->reference, $order->row_version, (string) Str::uuid());
            }
        });
    }

    /**
     * Plans en pause dont le délai de reprise est dépassé : arrêtés (jamais dus). Idempotent.
     *
     * @return int plans arrêtés
     */
    public function expireDue(): int
    {
        $cut = now()->subDays((int) config('freeci.missions.milestones.reopen_days'));
        $n = 0;
        foreach (DB::table('mission_plans')->where('state', 'paused')->where('paused_at', '<=', $cut)->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$n) {
                $plan = DB::table('mission_plans')->where('id', $id)->where('state', 'paused')->lockForUpdate()->first();
                if ($plan === null) {
                    return;
                }
                $title = (string) DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $plan->mission_id)->value('v.title');
                $this->endPlan($plan, 'stopped', 'unpaid', $title, 'Le jalon n’a pas été payé dans le délai de reprise : le plan est arrêté.');
                $n++;
            });
        }

        return $n;
    }

    private function endPlan(object $plan, string $state, string $reason, string $title, string $message): void
    {
        DB::table('mission_plans')->where('id', $plan->id)->update(['state' => $state, 'stop_reason' => $reason, 'paused_at' => null, 'ended_at' => now(), 'updated_at' => now()]);
        DB::table('mission_plan_items')->where('plan_id', $plan->id)->whereIn('state', ['upcoming', 'open'])->update(['state' => 'cancelled', 'updated_at' => now()]);
        foreach ([$plan->client_id, $plan->freelancer_id] as $u) {
            ($this->notify)((string) $u, 'milestone_update', 'plan_stopped:'.$plan->id.':'.$u, 'Plan de jalons arrêté', "« {$title} » : ".$message, 'milestones.show', ['mission' => $plan->mission_id]);
        }
    }

    /** Crée la commande d'un jalon (suivant, ou rouvert) en reprenant l'accord, le brief et l'environnement de la commande du jalon 1 : conditions financières figées, rien à ressaisir. */
    private function openOrder(object $plan, object $item): Order
    {
        $firstItem = DB::table('mission_plan_items')->where('plan_id', $plan->id)->where('rank', 1)->first();
        $first = Order::query()->with(['agreement', 'brief'])->findOrFail($firstItem->order_id);
        $count = DB::table('mission_plan_items')->where('plan_id', $plan->id)->count();
        $title = (string) DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $plan->mission_id)->value('v.title');
        $now = now();
        $reference = 'FC-'.$now->format('ym').'-'.str_pad((string) DB::selectOne("select nextval('order_reference_seq') as n")->n, 5, '0', STR_PAD_LEFT);
        $order = Order::create([
            'reference' => $reference, 'client_id' => $plan->client_id, 'freelancer_id' => $plan->freelancer_id, 'service_id' => null, 'origin' => 'mission', 'mission_id' => $plan->mission_id,
            'proposal_version_id' => $plan->proposal_version_id, 'milestone_item_id' => $item->id, 'state' => OrderState::AwaitingPayment, 'requested_at' => $now, 'response_deadline_at' => $now, 'accepted_at' => $now,
            'is_demo' => $first->is_demo, 'environment' => PaymentMode::orderEnvironment(),
        ]);
        $agreement = $first->agreement->attributesToArray();
        unset($agreement['order_id'], $agreement['created_at']);
        $order->agreement()->create(array_merge($agreement, $this->agreementFor($item, $count, $title)));
        $order->brief()->create(['answers' => $first->brief->answers, 'notes' => $first->brief->notes]);
        $open = app(PaymentGate::class)->allows($order);
        $order->forceFill(['payment_deadline_at' => $open ? $now->copy()->addHours((int) $order->agreement->payment_hours) : null])->save();
        $order->events()->create(['type' => 'milestone_opened', 'actor_id' => null, 'to_state' => OrderState::AwaitingPayment->value, 'note' => "Jalon {$item->rank} sur {$count} : {$item->title}", 'meta' => ['rank' => $item->rank, 'price' => Money::xof((int) $item->price_xof)->formatted(), 'payment_open' => $open]]);
        DB::table('mission_plan_items')->where('id', $item->id)->update(['state' => 'open', 'order_id' => $order->getKey(), 'updated_at' => now()]);

        return $order;
    }
}
