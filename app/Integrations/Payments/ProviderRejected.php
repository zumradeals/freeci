<?php

namespace App\Integrations\Payments;

use RuntimeException;

/** Refus DÉFINITIF du prestataire (clés, validation…) : aucune transaction n'existe chez lui. À distinguer d'un résultat incertain (délai dépassé, 5xx). */
class ProviderRejected extends RuntimeException {}
