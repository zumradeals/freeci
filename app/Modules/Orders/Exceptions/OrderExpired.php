<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Délai dépassé : la commande est expirée. */
class OrderExpired extends RuntimeException {}
