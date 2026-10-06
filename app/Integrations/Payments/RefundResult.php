<?php

namespace App\Integrations\Payments;

/**
 * Résultat d'un appel de remboursement. `confirmed` : le prestataire a répondu 200 avec un corps cohérent (référence, statut « refunded », montant, devise, environnement).
 * `rejected` : refus DÉFINITIF (aucun remboursement n'a eu lieu). `uncertain` : tout le reste (délai dépassé, 5xx, 409, corps incohérent) — jamais « effectué ».
 * `not_sent` : aucun appel émis (configuration).
 */
final readonly class RefundResult
{
    public function __construct(
        public string $outcome,                 // confirmed | rejected | uncertain | not_sent
        public string $code = '',               // code technique court (jamais le corps du prestataire)
        public ?string $refundReference = null,
        public ?int $amountRefunded = null,
    ) {}

    public static function confirmed(string $refundReference, int $amount): self
    {
        return new self('confirmed', 'ok', $refundReference, $amount);
    }

    public static function rejected(string $code): self
    {
        return new self('rejected', $code);
    }

    public static function uncertain(string $code): self
    {
        return new self('uncertain', $code);
    }

    public static function notSent(string $code): self
    {
        return new self('not_sent', $code);
    }
}
