<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/** L'action n'est plus possible dans l'état courant du service (modifié entre-temps, déjà traité…). Le message est lisible. */
class ServiceStateConflict extends RuntimeException {}
