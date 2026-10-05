<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Ce service n'accepte pas de demande. */
class RequestsClosed extends RuntimeException {}
