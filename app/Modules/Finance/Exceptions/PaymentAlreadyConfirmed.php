<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/** Le paiement de cette commande est déjà confirmé. */
class PaymentAlreadyConfirmed extends RuntimeException {}
