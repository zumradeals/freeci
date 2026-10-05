<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Une demande est déjà en attente pour ce service. */
class PendingRequestExists extends RuntimeException {}
