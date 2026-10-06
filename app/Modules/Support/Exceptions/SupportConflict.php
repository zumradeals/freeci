<?php

namespace App\Modules\Support\Exceptions;

use RuntimeException;

/** Règle du dossier non satisfaite (état, droit, conflit d'intérêts, double décision…). Le message est montré tel quel : il ne contient aucun contenu privé. */
class SupportConflict extends RuntimeException {}
