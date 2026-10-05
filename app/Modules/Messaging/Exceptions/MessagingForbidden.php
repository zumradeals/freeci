<?php

namespace App\Modules\Messaging\Exceptions;

use RuntimeException;

/** Conversation, contexte ou fichier inexistant OU hors de portée de l'utilisateur : même réponse (404). */
class MessagingForbidden extends RuntimeException {}
