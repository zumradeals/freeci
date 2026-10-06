<?php

namespace App\Modules\Notifications\Actions;

use App\Modules\Catalog\Models\ServiceEvent;
use App\Modules\Missions\Models\MissionEvent;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Transforme les événements métier (lignes d'historique en ajout seul) en notifications. Une ligne d'historique existe une seule fois,
 * et la clé de déduplication en porte l'identifiant : un événement rejoué ne notifie jamais deux fois. Une défaillance de notification ne
 * doit jamais faire échouer l'action métier (elle est journalisée sans contenu).
 */
final class NotificationRouter
{
    public function __construct(private Notify $notify) {}

    public function order(OrderEvent $e): void
    {
        $this->safely(function () use ($e) {
            $o = Order::query()->with('agreement')->find($e->order_id);
            if ($o === null) {
                return;
            }
            $ref = $o->reference;
            $title = $o->agreement->service_title;
            $client = $o->client_id;
            $free = $o->freelancer_id;
            $other = $e->actor_id === $client ? $free : $client;
            $to = fn (string $user, string $type, string $text) => ($this->notify)($user, $type, 'order_event:'.$e->id, $text, "{$title} · {$ref}", 'orders.show', ['reference' => $ref]);

            match ($e->type) {
                'requested' => $to($free, 'order_requested', 'Nouvelle demande de prestation'),
                'accepted' => $to($client, 'order_accepted', 'Demande acceptée : la commande attend votre paiement'),
                'declined' => $to($client, 'order_declined', 'Demande refusée'),
                'withdrawn' => $to($free, 'order_withdrawn', 'Demande retirée par le client'),
                'cancelled' => $to($other, 'order_cancelled', 'Commande annulée avant paiement'),
                'expired' => $this->both($to, $client, $free, 'order_expired', 'Délai dépassé : commande expirée'),
                'proposal_selected' => $to($free, 'proposal_selected', 'Votre proposition est retenue'),
                'payment_confirmed' => $this->both($to, $client, $free, 'payment_confirmed', 'Paiement confirmé'),
                'brief_awaited' => $to($client, 'brief_awaited', 'Complétez le brief pour lancer le travail'),
                'work_started' => $this->both($to, $client, $free, 'work_started', 'Le travail démarre'),
                'delivery_submitted' => $to($client, 'delivery_submitted', 'Livraison v'.($e->meta['version'] ?? '?').' à examiner'),
                'correction_requested' => $to($free, 'correction_requested', 'Correction demandée'),
                'extension_requested' => $to($client, 'extension_requested', 'Report d’échéance proposé : votre décision est attendue'),
                'extension_accepted' => $to($free, 'extension_decided', 'Report d’échéance accepté'),
                'extension_declined' => $to($free, 'extension_decided', 'Report d’échéance refusé'),
                'validated' => $to($free, 'order_validated', 'Livraison validée : commande clôturée'),
                'disagreement_reported' => $to($free, 'disagreement_reported', 'Le client signale un désaccord'),
                'review_overdue' => $this->both($to, $client, $free, 'review_overdue', 'Délai d’examen dépassé : rien n’est validé automatiquement'),
                default => null,
            };
        });
    }

    public function service(ServiceEvent $e): void
    {
        $this->safely(function () use ($e) {
            $texts = ['approved' => 'est publié', 'changes_requested' => ': correction demandée par la modération', 'suspended' => 'est suspendu par la modération', 'reinstated' => 'est remis en ligne par la modération'];
            if (! isset($texts[$e->type])) {
                return;
            }
            $row = DB::table('services')->join('freelance_profiles', 'freelance_profiles.id', '=', 'services.freelance_profile_id')->where('services.id', $e->service_id)->first(['freelance_profiles.user_id', 'services.title']);
            if ($row === null) {
                return;
            }
            $title = $e->version_id ? (string) DB::table('service_versions')->where('id', $e->version_id)->value('title') : $row->title;
            ($this->notify)($row->user_id, 'moderation_decision', 'service_event:'.$e->id, 'Votre service « '.mb_substr($title ?: $row->title, 0, 80).' » '.$texts[$e->type], null, 'freelance.services');
        });
    }

    public function mission(MissionEvent $e): void
    {
        $this->safely(function () use ($e) {
            $m = DB::table('missions')->where('id', $e->mission_id)->first(['client_id']);
            if ($m === null) {
                return;
            }
            $title = (string) DB::table('mission_versions')->join('missions', 'missions.id', '=', 'mission_versions.mission_id')->where('missions.id', $e->mission_id)->orderByDesc('mission_versions.number')->value('mission_versions.title');
            $t = mb_substr($title, 0, 80);
            $key = 'mission_event:'.$e->id;
            $go = fn (string $type, string $text, string $route = 'client.missions.show') => ($this->notify)($m->client_id, $type, $key, $text, null, $route, ['mission' => $e->mission_id]);
            match ($e->type) {
                'approved' => $go('moderation_decision', "Votre mission « {$t} » est publiée"),
                'changes_requested' => $go('moderation_decision', "Correction demandée sur votre mission « {$t} »"),
                'suspended' => $go('moderation_decision', "Votre mission « {$t} » est suspendue par la modération"),
                'reinstated' => $go('moderation_decision', "Votre mission « {$t} » est remise en ligne par la modération"),
                'expired' => $go('mission_ended', "Votre mission « {$t} » a expiré sans proposition retenue"),
                'reservation_ended' => $go('mission_ended', "Mission « {$t} » : sélection terminée, à rouvrir ou fermer"),
                'proposal_submitted' => $go('proposal_received', "Nouvelle proposition sur « {$t} »", 'client.missions.proposals'),
                'proposal_revised' => $go('proposal_updated', "Proposition révisée sur « {$t} »", 'client.missions.proposals'),
                default => null,
            };
        });
    }

    private function both(callable $to, string $a, string $b, string $type, string $text): void
    {
        $to($a, $type, $text);
        $to($b, $type, $text);
    }

    private function safely(callable $do): void
    {
        try {
            $do();
        } catch (Throwable $e) {
            report($e);          // jamais de contenu de message ni de secret dans le contexte
        }
    }
}
