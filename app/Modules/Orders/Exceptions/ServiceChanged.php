<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Le service a changé depuis sa consultation (409) : relire les conditions avant d'envoyer. */
class ServiceChanged extends RuntimeException {}
