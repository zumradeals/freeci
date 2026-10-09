<?php

namespace App\Integrations\Payments;

/** Notification AUTHENTIFIÉE (signature valide) dont le corps n'est pas exploitable : réponse 400 (et non 401, qui laisserait croire à un secret erroné). */
final class InvalidProviderBody extends InvalidProviderEvent {}
