<?php

namespace App\Modules\Support\Support;

use Illuminate\Support\Facades\DB;

/** Vocabulaire, états admis et conflits d'intérêts des dossiers d'assistance. Aucune règle d'arbitrage n'est définie ici : les décisions sont humaines. */
final class CaseRules
{
    public const KINDS = [
        'support' => 'Demande d’assistance', 'report' => 'Signalement', 'dispute' => 'Litige', 'cancellation' => 'Demande d’annulation après paiement',
        'claim' => 'Réclamation après versement', 'follow_up' => 'Besoin de suivi',
    ];

    public const DISPUTE_KINDS = ['dispute', 'cancellation', 'claim'];

    public const LIVE = ['open', 'in_review', 'awaiting_requester', 'awaiting_party'];

    /** États de commande depuis lesquels un litige / une annulation peut être demandé (commande payée, reversement non exécuté). */
    public const DISPUTE_STATES = ['in_progress', 'delivered', 'revision_requested', 'validated', 'closed'];

    public const CANCEL_STATES = ['awaiting_brief', 'in_progress', 'delivered', 'revision_requested'];

    public const STAFF_STATUS = [
        'open' => 'Ouvert', 'in_review' => 'En cours d’examen', 'awaiting_requester' => 'En attente du demandeur', 'awaiting_party' => 'En attente de l’autre partie',
        'decided' => 'Décision rendue', 'closed' => 'Clos',
    ];

    public const SUPPORT_CATEGORIES = ['order' => 'Une commande', 'account' => 'Mon compte', 'payment' => 'Un paiement', 'technical' => 'Un problème technique', 'other' => 'Autre'];

    public const REPORT_REASONS = ['fraud' => 'Fraude ou arnaque', 'abuse' => 'Propos abusifs ou harcèlement', 'illegal' => 'Contenu illicite', 'private_contact' => 'Échange de coordonnées pour contourner la plateforme', 'false_info' => 'Informations fausses ou trompeuses', 'other' => 'Autre'];

    public const TARGETS = ['profile' => 'Profil', 'service' => 'Service', 'mission' => 'Mission', 'message' => 'Message', 'review' => 'Avis', 'reply' => 'Réponse à un avis'];

    public const OUTCOMES = ['continue' => 'Poursuite de la prestation', 'validate_delivery' => 'Résolution du désaccord : livraison jugée conforme', 'cancel' => 'Annulation motivée de la commande', 'answered' => 'Réclamation examinée'];

    public const FINANCIAL = ['none' => 'Aucune suite financière', 'release' => 'Reversement à autoriser (à traiter)', 'refund' => 'Remboursement à traiter', 'partial' => 'Répartition à fixer (à traiter)'];

    public static function statusFor(string $status, string $kind, bool $assigned): string
    {
        return match (true) {
            $status === 'open' && ! $assigned => 'Reçu : pas encore pris en charge',
            $status === 'open', $status === 'in_review' => 'Pris en charge : en cours d’examen par l’équipe',
            $status === 'awaiting_requester' => 'L’équipe attend votre réponse',
            $status === 'awaiting_party' => 'L’équipe attend l’autre partie',
            $status === 'decided' => 'Décision rendue',
            default => 'Clos',
        };
    }

    /** Ce membre du personnel est-il impliqué dans le dossier (demandeur, autre partie, partie de la commande, auteur de la cible) ? Dans ce cas : aucun traitement. */
    public static function involves(string $staffId, object $case): bool
    {
        if (in_array($staffId, array_filter([$case->requester_id, $case->counterparty_id]), true)) {
            return true;
        }
        if ($case->order_id !== null) {
            $o = DB::table('orders')->where('id', $case->order_id)->first(['client_id', 'freelancer_id']);
            if ($o !== null && in_array($staffId, [$o->client_id, $o->freelancer_id], true)) {
                return true;
            }
        }

        return $case->target_type !== null && self::targetOwner($case->target_type, (string) $case->target_id) === $staffId;
    }

    public static function targetOwner(string $type, string $id): ?string
    {
        return match ($type) {
            'profile' => DB::table('freelance_profiles')->where('id', $id)->value('user_id'),
            'service' => DB::table('services')->join('freelance_profiles as p', 'p.id', '=', 'services.freelance_profile_id')->where('services.id', $id)->value('p.user_id'),
            'mission' => DB::table('missions')->where('id', $id)->value('client_id'),
            'message' => DB::table('messages')->where('id', (int) $id)->value('sender_id'),
            'review' => DB::table('reviews')->where('id', $id)->value('author_id'),
            'reply' => DB::table('review_responses')->join('reviews', 'reviews.id', '=', 'review_responses.review_id')->where('reviews.id', $id)->value('review_responses.author_id'),
            default => null,
        };
    }
}
