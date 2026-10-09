<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Le freelance a indiqué qu'il ne prend pas de nouvelle demande pour le moment (disponibilité, F-10). */
class SellerUnavailable extends RuntimeException {}
