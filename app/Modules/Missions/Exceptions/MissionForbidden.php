<?php

namespace App\Modules\Missions\Exceptions;

use RuntimeException;

/** Mission ou proposition inexistante OU hors de portée de l'utilisateur : même réponse (404). */
class MissionForbidden extends RuntimeException {}
