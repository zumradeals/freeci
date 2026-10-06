<?php

namespace App\Modules\Files\Exceptions;

use RuntimeException;

/** Le dépôt de fichiers est désactivé : aucun service de contrôle de sécurité n'est disponible sur cette installation. */
class FilesDisabled extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Le dépôt de fichiers est temporairement indisponible. L’administration doit rétablir le contrôle de sécurité ; un texte ne remplace pas un fichier obligatoire.');
    }
}
