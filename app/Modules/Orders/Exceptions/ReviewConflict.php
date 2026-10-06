<?php

namespace App\Modules\Orders\Exceptions;

/** Règle d'un avis ou d'une réponse non satisfaite (message montré à l'utilisateur, sans donnée sensible). */
class ReviewConflict extends \DomainException {}
