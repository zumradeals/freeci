<?php

namespace App\Shared;

/** Montant en francs CFA (XOF), entier : aucun flottant (docs/02 §4.3). */
final readonly class Money
{
    private function __construct(public int $xof) {}

    public static function xof(int $amount): self
    {
        return new self($amount);
    }

    /** « 100 000 » avec espace insécable fine entre les milliers (le suffixe « FCFA » est ajouté par la vue). */
    public function formatted(): string
    {
        return number_format($this->xof, 0, ',', "\u{202F}");
    }
}
