<?php

return [
    // Les jeux de données fictives ne s'installent jamais en production sans choix explicite.
    'allow_demo_seed' => (bool) env('FREECI_ALLOW_DEMO_SEED', false),

    // Mot de passe du compte client de démonstration. Vide = généré au hasard à chaque amorçage et affiché une fois.
    'demo_client_password' => env('FREECI_DEMO_CLIENT_PASSWORD'),

    // Bandeau « Démonstration » (données fictives, fonctions inachevées). À désactiver seulement quand
    // le contenu est réel ET que toutes les fonctions affichées sont opérationnelles.
    'demo_banner' => (bool) env('FREECI_DEMO_BANNER', true),

    // Demande aux moteurs de recherche de ne pas indexer (en-tête X-Robots-Tag + balise meta).
    'noindex' => (bool) env('FREECI_NOINDEX', true),

    // Durée HSTS en secondes (0 = pas d'en-tête). Appliqué seulement sur une requête HTTPS, sans « includeSubDomains »
    // ni « preload » : FreeCI ne doit pas engager les autres sous-domaines du domaine parent.
    'hsts_max_age' => (int) env('FREECI_HSTS_MAX_AGE', 2592000),

];
