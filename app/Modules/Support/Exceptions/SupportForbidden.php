<?php

namespace App\Modules\Support\Exceptions;

use RuntimeException;

/** Dossier inexistant ou non accessible : même réponse dans les deux cas. */
class SupportForbidden extends RuntimeException {}
