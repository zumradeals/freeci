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

    'orders' => [
        // Délais de réponse (freelance) et de paiement (client), figés dans chaque accord à la demande (docs/04 §3.2).
        'response_hours' => (int) env('FREECI_RESPONSE_HOURS', 48),
        'payment_hours' => (int) env('FREECI_PAYMENT_HOURS', 24),
        // Version des conditions de demande affichées et acceptées (le texte juridique définitif reste à rédiger).
        'conditions_version' => '2026-10-v1',
        // Livraison : délai d'examen du client (jours). Son expiration enregistre un besoin de suivi ; elle ne valide ni ne clôture rien.
        'review_days' => (int) env('FREECI_REVIEW_DAYS', 7),
        // Report d'échéance : une proposition ne peut dépasser l'échéance actuelle de plus de N jours.
        'extension_max_days' => (int) env('FREECI_EXTENSION_MAX_DAYS', 30),
    ],

    'payments' => [
        // Simulateur de paiement : DÉSACTIVÉ par défaut. Même activé, il n'est utilisable que pour des commandes de
        // démonstration et des comptes de recette autorisés (App\Modules\Finance\SandboxGate). Aucun prestataire réel.
        'sandbox_enabled' => (bool) env('FREECI_PAYMENT_SANDBOX', false),
        // Secret HMAC des notifications simulées (vide = notifications refusées). Jamais dans le dépôt.
        'sandbox_webhook_secret' => env('FREECI_SANDBOX_WEBHOOK_SECRET'),
    ],

    // Gabarits et bornes de la création de services et de profils : PARAMÈTRES PROVISOIRES (docs/04 §4.2 ; à valider), non des règles commerciales définitives.
    'catalog' => [
        'title' => [15, 100], 'summary' => [30, 300], 'scope' => [150, 5000],
        'price_xof' => [5000, 500000], 'delivery_days' => [1, 60], 'revisions' => [0, 10],
        'deliverables_max' => 10, 'exclusions_max' => 10, 'client_inputs_max' => 8, 'line_max' => 200,
        'images_max' => 6, 'image_max_mb' => 5, 'image_max_pixels' => 16000000, 'image_min_width' => 400,
        'bio' => [50, 1500], 'skills_max' => 10, 'skill' => [2, 40],
    ],

    'files' => [
        // « clamav » = contrôle antivirus ; « none » (défaut) = aucun contrôle disponible : le dépôt de fichiers est désactivé.
        'scanner' => env('FREECI_FILE_SCANNER', 'none'),
        'clamscan_binary' => env('FREECI_CLAMSCAN_BINARY', '/usr/bin/clamdscan'),
        'max_mb' => (int) env('FREECI_UPLOAD_MAX_MB', 10),
        // Fichiers d'une livraison : même plafond par défaut (nginx et PHP-FPM doivent l'accepter, docs/11).
        'delivery_max_mb' => (int) env('FREECI_DELIVERY_MAX_MB', 10),
        'max_files' => 10,
        'max_total_mb' => 50,
    ],
];
