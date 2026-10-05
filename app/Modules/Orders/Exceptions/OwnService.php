<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** On ne commande pas son propre service. */
class OwnService extends RuntimeException {}
