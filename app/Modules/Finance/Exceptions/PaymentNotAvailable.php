<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/** Le paiement simulé n'est pas disponible pour cette commande (simulateur désactivé, commande ou compte non autorisé). */
class PaymentNotAvailable extends RuntimeException {}
