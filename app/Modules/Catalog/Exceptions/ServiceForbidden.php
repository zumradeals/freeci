<?php

namespace App\Modules\Catalog\Exceptions;

use RuntimeException;

/** Service inexistant OU appartenant à un autre compte : même réponse (404), rien n'est révélé. */
class ServiceForbidden extends RuntimeException {}
