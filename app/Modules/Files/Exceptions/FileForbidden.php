<?php

namespace App\Modules\Files\Exceptions;

use RuntimeException;

/** Fichier inexistant, non contrôlé ou interdit : même réponse dans tous les cas. */
class FileForbidden extends RuntimeException {}
