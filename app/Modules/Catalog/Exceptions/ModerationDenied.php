<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/** L'acteur n'a pas l'habilitation « administrateur » en vigueur, ou tente de modérer son propre service. */
class ModerationDenied extends RuntimeException {}
