<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Clé d'opération réutilisée avec un contenu différent. */
class OperationKeyReused extends RuntimeException {}
