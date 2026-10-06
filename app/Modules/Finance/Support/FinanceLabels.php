<?php

namespace App\Modules\Finance\Support;

/** Libellés d'affichage (français). Un état « demandé » ou « approuvé » n'est jamais présenté comme effectué. */
final class FinanceLabels
{
    public const STATES = [
        'requested' => 'Demandé', 'approved' => 'Approuvé', 'in_progress' => 'En cours', 'confirmed' => 'Confirmé', 'failed' => 'Échoué',
        'to_verify' => 'À vérifier', 'rejected' => 'Refusé', 'cancelled' => 'Annulé',
    ];

    public const TONES = [
        'requested' => 'neutral', 'approved' => 'info', 'in_progress' => 'warning', 'confirmed' => 'success', 'failed' => 'error', 'to_verify' => 'warning', 'rejected' => 'neutral', 'cancelled' => 'neutral',
    ];

    public const KINDS = ['refund' => 'Remboursement', 'payout' => 'Reversement'];

    /** Étiquette client / freelance : ce qu'une partie peut comprendre sans jargon, sans jamais annoncer un résultat non établi. */
    public const PARTY_STATES = [
        'requested' => 'Demandé : en attente d’approbation', 'approved' => 'Approuvé : en attente d’exécution', 'in_progress' => 'En cours auprès du prestataire',
        'confirmed' => 'Confirmé', 'failed' => 'Non abouti : aucun montant versé', 'to_verify' => 'En cours de vérification', 'rejected' => 'Refusé', 'cancelled' => 'Annulé',
    ];

    public static function state(string $s): string
    {
        return self::STATES[$s] ?? $s;
    }
}
