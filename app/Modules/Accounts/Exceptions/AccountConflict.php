<?php

namespace App\Modules\Accounts\Exceptions;

use RuntimeException;

/** Règle de gestion de compte non satisfaite : le message est destiné à l'utilisateur. */
class AccountConflict extends RuntimeException {}
