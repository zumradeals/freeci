<?php

namespace App\Modules\Files\Exceptions;

use RuntimeException;

/** Le dépôt de fichiers est désactivé : aucun service de contrôle de sécurité n'est disponible sur cette installation. */
class FilesDisabled extends RuntimeException {}
