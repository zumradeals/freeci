<?php

namespace App\Modules\Notifications\Support;

/**
 * Catalogue des notifications. « Indispensables » : liées au compte et aux commandes, toujours créées (et envoyées par courriel quand le
 * courrier est configuré), non désactivables. « Facultatives » : l'utilisateur règle séparément l'application et le courriel
 * (défaut : application activée, courriel désactivé).
 */
final class NotificationTypes
{
    public const ESSENTIAL = 'essential';

    public const OPTIONAL = 'optional';

    /** @return array<string, array{label: string, category: string, description: string}> */
    public static function all(): array
    {
        $e = self::ESSENTIAL;
        $o = self::OPTIONAL;

        return [
            'order_requested' => ['label' => 'Nouvelle demande de prestation', 'category' => $e, 'description' => 'Un client demande l’une de vos prestations.'],
            'order_accepted' => ['label' => 'Demande acceptée', 'category' => $e, 'description' => 'Le freelance accepte votre demande : la commande attend votre paiement.'],
            'order_declined' => ['label' => 'Demande refusée', 'category' => $e, 'description' => 'Le freelance refuse votre demande (avec son motif, dans l’application).'],
            'order_withdrawn' => ['label' => 'Demande retirée', 'category' => $e, 'description' => 'Un client retire sa demande.'],
            'order_cancelled' => ['label' => 'Commande annulée', 'category' => $e, 'description' => 'Une commande est annulée avant paiement.'],
            'order_expired' => ['label' => 'Délai dépassé', 'category' => $e, 'description' => 'Une demande ou un paiement expire.'],
            'proposal_selected' => ['label' => 'Proposition retenue', 'category' => $e, 'description' => 'Un client retient votre proposition : une commande est créée.'],
            'payment_confirmed' => ['label' => 'Paiement confirmé', 'category' => $e, 'description' => 'Le paiement d’une commande est confirmé côté serveur.'],
            'brief_awaited' => ['label' => 'Brief à compléter', 'category' => $e, 'description' => 'Le paiement est confirmé : le brief doit être complété avant le départ.'],
            'work_started' => ['label' => 'Travail démarré', 'category' => $e, 'description' => 'Le travail démarre ; l’échéance est enregistrée.'],
            'delivery_submitted' => ['label' => 'Livraison à examiner', 'category' => $e, 'description' => 'Le freelance soumet une livraison.'],
            'correction_requested' => ['label' => 'Correction demandée', 'category' => $e, 'description' => 'Le client demande une correction.'],
            'extension_requested' => ['label' => 'Report d’échéance proposé', 'category' => $e, 'description' => 'Le freelance propose un report : votre décision est attendue.'],
            'extension_decided' => ['label' => 'Réponse au report', 'category' => $e, 'description' => 'Le client accepte ou refuse le report proposé.'],
            'order_validated' => ['label' => 'Livraison validée', 'category' => $e, 'description' => 'Le client valide la livraison : la commande est clôturée.'],
            'disagreement_reported' => ['label' => 'Désaccord signalé', 'category' => $e, 'description' => 'Le client signale un désaccord après épuisement des corrections.'],
            'review_overdue' => ['label' => 'Délai d’examen dépassé', 'category' => $e, 'description' => 'Le délai d’examen d’une livraison est dépassé ; rien n’est validé automatiquement.'],
            'moderation_decision' => ['label' => 'Décision de modération', 'category' => $e, 'description' => 'Votre service ou votre mission est approuvé, à corriger ou suspendu.'],
            'account_status' => ['label' => 'Statut du compte', 'category' => $e, 'description' => 'Votre compte est suspendu ou réactivé.'],
            'support_update' => ['label' => 'Assistance', 'category' => $e, 'description' => 'Un nouveau message ou un changement sur votre dossier d’assistance (le contenu n’est jamais envoyé par courriel).'],
            'dispute_update' => ['label' => 'Litige ou annulation', 'category' => $e, 'description' => 'Un litige ou une demande d’annulation est ouvert, ou une décision est rendue sur votre commande.'],
            'mission_ended' => ['label' => 'Mission : action requise', 'category' => $e, 'description' => 'Une mission expire ou sa sélection se termine.'],
            'message_received' => ['label' => 'Nouveau message', 'category' => $o, 'description' => 'Vous recevez un message privé (le contenu n’est jamais envoyé par courriel).'],
            'proposal_received' => ['label' => 'Nouvelle proposition', 'category' => $o, 'description' => 'Un freelance répond à l’une de vos missions.'],
            'proposal_updated' => ['label' => 'Proposition révisée', 'category' => $o, 'description' => 'Un candidat révise ou reconfirme sa proposition.'],
        ];
    }

    public static function category(string $type): string
    {
        return self::all()[$type]['category'] ?? self::ESSENTIAL;
    }

    /** @return array<string, array{label: string, category: string, description: string}> */
    public static function optional(): array
    {
        return array_filter(self::all(), fn ($t) => $t['category'] === self::OPTIONAL);
    }
}
