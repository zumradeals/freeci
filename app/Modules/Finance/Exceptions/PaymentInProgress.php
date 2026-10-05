<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/** Une tentative de paiement est déjà ouverte (en cours ou incertaine) : aucune nouvelle tentative. */
class PaymentInProgress extends RuntimeException {}
