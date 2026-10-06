<?php

return [
    // Les jeux de données fictives ne s'installent jamais en production sans choix explicite.
    'allow_demo_seed' => (bool) env('FREECI_ALLOW_DEMO_SEED', false),

    // Mot de passe du compte client de démonstration. Vide = généré au hasard à chaque amorçage et affiché une fois.
    'demo_client_password' => env('FREECI_DEMO_CLIENT_PASSWORD'),

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

    // Passerelle de paiement : Genius Pay, UNIQUE. Trois réglages distincts, tous explicites côté serveur (aucun basculement automatique) :
    //  - le PRESTATAIRE : Genius Pay (aucun autre) ;
    //  - l'ENVIRONNEMENT des NOUVELLES commandes et des NOUVEAUX paiements : « sandbox » (défaut, commandes de TEST) ou « live » (argent réel) ;
    //  - l'AUTORISATION de créer de nouveaux paiements (faux par défaut). La désactiver n'interrompt jamais le suivi des tentatives existantes.
    // Chaque commande et chaque tentative conservent leur environnement : changer ces réglages ne convertit rien d'engagé.
    // Lot 12 — avis. Valeurs PROVISOIRES (CDC F36 : « après les deux dépôts ou quatorze jours » ; architecture §9 : « quatorze jours proposés »). Elles ne sont pas des décisions
    // approuvées ; la date de visibilité de chaque avis est FIGÉE à son dépôt (changer la valeur n'affecte pas les avis existants).
    'reviews' => [
        // Un avis déposé devient public au plus tôt N jours après la clôture commerciale de la commande (le dépôt du freelance n'existe pas dans ce lot : voir docs/20).
        'publication_days' => (int) env('FREECI_REVIEW_PUBLICATION_DAYS', 14),
        'comment_min' => 10,
        'comment_max' => 1500,
        'reply_max' => 1000,
        'per_page' => 10,
    ],

    // Lot 11 — conditions financières. TOUTES ces valeurs sont des PROPOSITIONS DE TRAVAIL (docs/01 « valeurs commerciales à confirmer »), jamais des décisions
    // commerciales approuvées. Le taux est FIGÉ dans chaque accord à sa création : modifier cette valeur n'affecte aucune commande existante.
    'finance' => [
        'commission_bp' => (int) env('FREECI_COMMISSION_BP', 1000),                      // points de base : 1 000 = 10 % (proposition, non validée)
        'commission_policy' => env('FREECI_COMMISSION_POLICY', 'proposition-non-validee'),   // étiquette conservée dans l'accord
        // Aucune API de reversement n'est documentée par Genius Pay : constante, pas une option. Les remboursements PARTIELS par API ne sont pas exécutés non plus
        // (règles des remboursements successifs et idempotence par montant non établies par la documentation).
        'refund_api_total_only' => true,
    ],

    'payments' => [
        'mode' => env('FREECI_PAYMENT_MODE', 'sandbox'),
        'enabled' => (bool) env('FREECI_PAYMENTS_ENABLED', false),
        // Le mode LIVE exige en plus cette autorisation explicite du porteur, des clés live, le compte marchand live attendu et leur secret de webhook.
        'live_authorized' => (bool) env('FREECI_LIVE_PAYMENTS_AUTHORIZED', false),
        'genius' => [
            'base_url' => env('GENIUSPAY_BASE_URL', 'https://geniuspay.ci/api/v1/merchant'),
            // Les reprises documentées vont jusqu'à 24 h : fenêtre de fraîcheur de la signature = 25 h par défaut (hypothèse à confirmer, docs/18).
            'webhook_tolerance' => (int) env('GENIUSPAY_WEBHOOK_TOLERANCE_SECONDS', 90000),
            'checkout_hosts' => env('GENIUSPAY_CHECKOUT_HOSTS', 'geniuspay.ci'),
            'timeout' => 15,
            // Jeux de clés DISTINCTS par environnement : uniquement côté serveur (.env, hors Git).
            'sandbox' => [
                'api_key' => env('GENIUSPAY_SANDBOX_API_KEY'), 'api_secret' => env('GENIUSPAY_SANDBOX_API_SECRET'),
                'webhook_secret' => env('GENIUSPAY_SANDBOX_WEBHOOK_SECRET'), 'merchant_id' => env('GENIUSPAY_SANDBOX_MERCHANT_ID'),
            ],
            'live' => [
                'api_key' => env('GENIUSPAY_LIVE_API_KEY'), 'api_secret' => env('GENIUSPAY_LIVE_API_SECRET'),
                'webhook_secret' => env('GENIUSPAY_LIVE_WEBHOOK_SECRET'), 'merchant_id' => env('GENIUSPAY_LIVE_MERCHANT_ID'),
            ],
        ],
    ],

    // Gabarits et bornes de la création de services et de profils : PARAMÈTRES PROVISOIRES (docs/04 §4.2 ; à valider), non des règles commerciales définitives.
    'catalog' => [
        'title' => [15, 100], 'summary' => [30, 300], 'scope' => [150, 5000],
        'price_xof' => [5000, 500000], 'delivery_days' => [1, 60], 'revisions' => [0, 10],
        'deliverables_max' => 10, 'exclusions_max' => 10, 'client_inputs_max' => 8, 'line_max' => 200,
        'images_max' => 6, 'image_max_mb' => 5, 'image_max_pixels' => 16000000, 'image_min_width' => 400,
        'bio' => [50, 1500], 'skills_max' => 10, 'skill' => [2, 40],
    ],

    // Missions et propositions : PARAMÈTRES PROVISOIRES (docs/04 §4), non des règles commerciales approuvées.
    'missions' => [
        'title' => [15, 100], 'description' => [150, 5000], 'budget_xof' => [5000, 5000000], 'deadline_max_days' => 60,
        'client_inputs_max' => 8, 'line_max' => 200,
        // Après la date limite de candidature, le client dispose de N jours pour choisir ; passé ce délai la mission expire.
        'selection_days' => 14,
        'proposal' => ['price_xof' => [5000, 5000000], 'delivery_days' => [1, 180], 'revisions' => [0, 10], 'scope' => [50, 3000], 'validity_days' => [1, 30], 'deliverables_max' => 10],
    ],

    // Notifications et messagerie. Les courriels ne partent que si le courrier est RÉELLEMENT configuré (MAIL_MAILER ≠ log/array).
    'notifications' => [
        'emails' => (bool) env('FREECI_NOTIFICATION_EMAILS', true),
        // Sans processus de file dédié, le planificateur peut vider la file chaque minute (cron « schedule:run » requis).
        'queue_via_scheduler' => (bool) env('FREECI_QUEUE_VIA_SCHEDULER', false),
    ],
    // Administration : accès soumis à une adresse vérifiée, à la double authentification, et à une confirmation récente pour les actes sensibles.
    'admin' => ['mfa_session_minutes' => 480, 'reauth_minutes' => 10, 'mfa_attempts' => 5, 'mfa_lock_minutes' => 15, 'page_size' => 25, 'issuer' => 'FreeCI'],
    // Assistance, signalements, litiges : bornes provisoires.
    'support' => ['body_max' => 4000, 'per_day' => 10, 'messages_per_hour' => 30, 'max_files' => 20, 'page_size' => 25],
    'messaging' => ['body_max' => 4000, 'per_10_minutes' => 30, 'page_size' => 20],

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

    // Gestion du compte (lot 13). Valeurs PROVISOIRES, non approuvées par le porteur : voir docs/21.
    'account' => [
        'closure_grace_days' => (int) env('FREECI_CLOSURE_GRACE_DAYS', 14),     // délai de réflexion avant anonymisation : PROVISOIRE
        'email_change_minutes' => 60,
        'export_per_day' => 5,
    ],

    // Identité de l'exploitant et pages d'information : AUCUNE valeur inventée. Vide = « à renseigner » (brouillon).
    'legal' => [
        'operator_name' => env('FREECI_OPERATOR_NAME'),
        'operator_address' => env('FREECI_OPERATOR_ADDRESS'),
        'operator_registration' => env('FREECI_OPERATOR_REGISTRATION'),
        'publication_director' => env('FREECI_PUBLICATION_DIRECTOR'),
        'host' => env('FREECI_HOST_NAME'),
        'contact_email' => env('FREECI_CONTACT_EMAIL'),
        // Pages dont le TEXTE a été adopté par le porteur (liste séparée par des virgules : conditions,confidentialite,mentions-legales,aide,contact,fonctionnement).
        'approved' => array_filter(array_map('trim', explode(',', (string) env('FREECI_PAGES_APPROVED', '')))),
    ],

    // Exploitation : dossier où deploy/backup.sh écrit son fichier d'état (lecture seule côté application).
    'ops' => [
        'backup_dir' => env('FREECI_BACKUP_DIR', '/var/backups/freeci'),
        'backup_max_age_hours' => 36,
    ],
];
