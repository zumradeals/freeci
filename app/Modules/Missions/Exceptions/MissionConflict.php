<?php

namespace App\Modules\Missions\Exceptions;

use RuntimeException;

/** L'action n'est plus possible dans l'état courant (modifié entre-temps, déjà traité…). Message lisible, rien n'a été modifié. */
class MissionConflict extends RuntimeException {}
