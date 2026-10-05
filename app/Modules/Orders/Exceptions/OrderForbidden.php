<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Accès interdit ou commande inexistante : même réponse dans les deux cas, rien n'est révélé. */
class OrderForbidden extends RuntimeException {}
