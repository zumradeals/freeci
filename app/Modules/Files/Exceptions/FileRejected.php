<?php

namespace App\Modules\Files\Exceptions;

use RuntimeException;

/** Fichier refusé (format, taille ou contenu non autorisé). */
class FileRejected extends RuntimeException {}
