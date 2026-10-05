<?php

namespace App\Modules\Messaging\Exceptions;

use RuntimeException;

/** Envoi impossible dans l'état courant (contact bloqué, débit trop élevé…). Message lisible ; rien n'a été enregistré. */
class MessagingConflict extends RuntimeException {}
