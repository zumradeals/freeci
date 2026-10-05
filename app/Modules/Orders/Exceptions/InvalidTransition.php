<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Transition impossible depuis l'état courant, ou commande modifiée entre-temps (409). */
class InvalidTransition extends RuntimeException {}
