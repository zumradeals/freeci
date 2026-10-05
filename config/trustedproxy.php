<?php

// Lu par le middleware de proxys de confiance de Laravel. Vide = aucun proxy (le serveur web est exposé directement).
// Valeur : adresses IP séparées par des virgules, ou « * » (à n'utiliser que si le serveur d'application n'est joignable que via le proxy).
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
