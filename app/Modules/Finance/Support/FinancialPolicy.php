<?php

namespace App\Modules\Finance\Support;

/**
 * Règles de calcul EXPLICITES (docs/02 §4.3, architecture §10). Montants : entiers FCFA, aucun flottant.
 * Les valeurs configurables (taux, seuil) sont des PROPOSITIONS non validées ; le taux d'une commande est celui FIGÉ dans son accord.
 */
final class FinancialPolicy
{
    /** Commission = (base × bp + 5 000) / 10 000, division entière : arrondi au franc le plus proche, demi-franc vers le haut. */
    public static function commission(int $baseXof, int $basisPoints): int
    {
        return intdiv($baseXof * $basisPoints + 5000, 10000);
    }

    /** Part du freelance = base − commission (la commission est arrondie, le reste est le complément : la somme est toujours exacte). */
    public static function freelancerShare(int $baseXof, int $basisPoints): int
    {
        return $baseXof - self::commission($baseXof, $basisPoints);
    }

    /** Nom de compte du registre : suffixe « _simulated » pour tout ce qui vient du mode test (jamais confondu avec du réel). */
    public static function account(string $base, bool $simulated): string
    {
        return $simulated ? $base.'_simulated' : $base;
    }

    /** Empreinte de l'action exacte (approbation liée à : opération, commande, paiement, montant, bénéficiaire, environnement). */
    public static function fingerprint(string $kind, string $orderId, string $paymentId, int $amountXof, ?string $beneficiaryId, string $environment, string $scope): string
    {
        return hash('sha256', implode('|', [$kind, $scope, $orderId, $paymentId, $amountXof, $beneficiaryId ?? '-', $environment]));
    }
}
