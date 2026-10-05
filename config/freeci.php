<?php

return [
    // Les jeux de données fictives ne s'installent jamais en production sans choix explicite.
    'allow_demo_seed' => (bool) env('FREECI_ALLOW_DEMO_SEED', false),

    // Mot de passe du compte client de démonstration. Vide = généré au hasard à chaque amorçage et affiché une fois.
    'demo_client_password' => env('FREECI_DEMO_CLIENT_PASSWORD'),
];
