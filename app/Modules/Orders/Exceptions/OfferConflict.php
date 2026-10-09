<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

/** Une offre personnalisée ne peut pas être envoyée, retirée, refusée ou acceptée dans son état actuel (message destiné à la personne). */
class OfferConflict extends RuntimeException {}
