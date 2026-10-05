<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Règle métier non respectée : le message est destiné à l'utilisateur (aucune donnée sensible). */
class RuleViolation extends RuntimeException
{
    /** @param list<string> $reasons */
    public function __construct(string $message, public readonly array $reasons = [])
    {
        parent::__construct($message);
    }
}
