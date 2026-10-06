<?php

namespace App\Modules\Finance\Support;

/** Règle financière non satisfaite (message destiné au personnel, sans donnée sensible). Étend DomainException : journalisé « refusé » par le journal d'audit. */
class FinanceConflict extends \DomainException {}
