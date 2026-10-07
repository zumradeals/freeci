<?php

namespace App\Modules\Admin\Settings;

use App\Modules\Admin\Navigation\Destinations;

/**
 * Registre des paramètres administrables. Chaque paramètre vise un chemin de configuration (par défaut `freeci.<clé>`) : le code existant le lit inchangé,
 * l'administration ne fait que SUPERPOSER une valeur saisie (statut provisoire / approuvé, auteur, motif, historique). Sans valeur saisie, la valeur
 * par défaut (code ou .env) s'applique.
 * Les SECRETS (mot de passe de courrier, clés Genius Pay) sont chiffrés avec APP_KEY, en écriture seule : jamais réaffichés, jamais journalisés.
 * N'en font pas partie, car nécessaires AVANT la lecture de la base : APP_KEY, identifiants de la base, APP_URL, APP_ENV, cookies de session.
 */
final class SettingDefinitions
{
    /** @return array<string, array{title: string, intro: string, financial?: bool, danger?: bool, approvable: bool, keys: list<string>}> */
    public static function groups(): array
    {
        $r = fn (string $base, array $parts) => array_merge(...array_map(fn ($p) => [$base.'.'.$p.'.0', $base.'.'.$p.'.1'], $parts));

        return [
            'commission' => ['title' => 'Commission', 'approvable' => true, 'financial' => true,
                'intro' => 'Part prélevée par FreeCI sur chaque commande. Le taux est figé dans l’accord de chaque commande à sa création : le modifier ne change aucune commande existante.',
                'keys' => ['finance.commission_bp']],
            'delais' => ['title' => 'Délais', 'approvable' => true,
                'intro' => 'Délais appliqués aux commandes, aux avis et à la fermeture de compte. Les délais d’une commande existante restent ceux figés dans son accord.',
                'keys' => ['orders.response_hours', 'orders.payment_hours', 'orders.review_days', 'orders.extension_max_days', 'missions.selection_days', 'reviews.publication_days', 'account.closure_grace_days']],
            'prix' => ['title' => 'Prix des services', 'approvable' => true,
                'intro' => 'Bornes de prix acceptées lors de la création d’un service. Les services déjà publiés ne sont pas modifiés.',
                'keys' => ['catalog.price_xof.0', 'catalog.price_xof.1']],
            'paiement' => ['title' => 'Paiement Genius Pay', 'approvable' => false, 'financial' => true, 'danger' => true,
                'intro' => 'Mode et clés de la passerelle de paiement. Les clés sont chiffrées, jamais réaffichées : saisissez-en une nouvelle pour la remplacer. Chaque commande et chaque paiement gardent leur environnement : changer le mode ne convertit rien d’engagé.',
                'keys' => ['payments.mode', 'payments.enabled', 'payments.live_authorized', 'payments.genius.base_url', 'payments.genius.checkout_hosts', 'payments.genius.webhook_tolerance',
                    'payments.genius.sandbox.api_key', 'payments.genius.sandbox.api_secret', 'payments.genius.sandbox.webhook_secret', 'payments.genius.sandbox.merchant_id',
                    'payments.genius.live.api_key', 'payments.genius.live.api_secret', 'payments.genius.live.webhook_secret', 'payments.genius.live.merchant_id']],
            'courrier' => ['title' => 'Courrier (SMTP)', 'approvable' => false,
                'intro' => 'Serveur d’envoi des courriels (réinitialisation de mot de passe, vérification d’adresse, notifications). Le mot de passe est chiffré et jamais réaffiché.',
                'keys' => ['mail.default', 'mail.host', 'mail.port', 'mail.scheme', 'mail.username', 'mail.password', 'mail.from_address', 'mail.from_name']],
            'fichiers' => ['title' => 'Fichiers', 'approvable' => false,
                'intro' => 'Contrôle antivirus et tailles maximales. Le serveur web et PHP doivent aussi accepter ces tailles (voir « État et préparation »).',
                'keys' => ['files.scanner', 'files.clamscan_binary', 'files.max_mb', 'files.delivery_max_mb', 'files.max_files', 'files.max_total_mb']],
            'catalogue' => ['title' => 'Services et profils : limites', 'approvable' => false,
                'intro' => 'Longueurs, nombres et bornes appliqués à la rédaction des services et des profils. Les contenus déjà publiés ne sont pas modifiés.',
                'keys' => array_merge($r('catalog', ['title', 'summary', 'scope', 'delivery_days', 'revisions', 'bio', 'skill']), ['catalog.deliverables_max', 'catalog.exclusions_max', 'catalog.client_inputs_max', 'catalog.line_max', 'catalog.images_max', 'catalog.image_max_mb', 'catalog.image_min_width', 'catalog.skills_max'])],
            'missions' => ['title' => 'Missions et propositions : limites', 'approvable' => false,
                'intro' => 'Bornes de rédaction des missions et des propositions.',
                'keys' => array_merge($r('missions', ['title', 'description', 'budget_xof']), ['missions.deadline_max_days', 'missions.client_inputs_max', 'missions.line_max'], $r('missions.proposal', ['price_xof', 'delivery_days', 'revisions', 'scope', 'validity_days']), ['missions.proposal.deliverables_max'])],
            'echanges' => ['title' => 'Avis, messagerie et assistance', 'approvable' => false,
                'intro' => 'Longueurs et cadences (protection contre les abus).',
                'keys' => ['reviews.comment_min', 'reviews.comment_max', 'reviews.reply_max', 'reviews.per_page', 'messaging.body_max', 'messaging.per_10_minutes', 'support.subjects.order', 'support.subjects.account', 'support.subjects.payment', 'support.subjects.technical', 'support.subjects.other', 'support.body_max', 'support.per_day', 'support.messages_per_hour', 'support.max_files']],
            'securite' => ['title' => 'Sécurité des accès', 'approvable' => false,
                'intro' => 'Durées et tentatives de la double authentification et des actes sensibles. Des valeurs trop permissives affaiblissent la protection de l’administration.',
                'keys' => ['admin.mfa_session_minutes', 'admin.reauth_minutes', 'admin.mfa_attempts', 'admin.mfa_lock_minutes', 'admin.page_size', 'account.export_per_day', 'account.email_change_minutes']],
            'exploitant' => ['title' => 'Exploitant', 'approvable' => false,
                'intro' => 'Identité et coordonnées affichées dans les mentions légales, la confidentialité et la page contact. Rien n’est affiché tant que ce n’est pas renseigné.',
                'keys' => ['legal.operator_name', 'legal.operator_address', 'legal.operator_registration', 'legal.publication_director', 'legal.host', 'legal.contact_email']],
            'courriels' => ['title' => 'Courriels', 'approvable' => false,
                'intro' => 'Les phrases des courriels envoyés par FreeCI. Un champ vide reprend le texte de départ. Les liens, la durée de validité du lien de confirmation et le fait qu’aucun message privé n’est jamais envoyé ne se modifient pas ici.',
                'keys' => ['mailtpl.subject_prefix', 'mailtpl.greeting', 'mailtpl.signature', 'mailtpl.verify_intro', 'mailtpl.verify_ignore', 'mailtpl.notification_intro', 'mailtpl.notification_note', 'mailtpl.account_warning']],
            'vitrine' => ['title' => 'Accueil et vitrine', 'approvable' => false,
                'intro' => 'Les textes de la page d’accueil et du pied de page. Un champ laissé vide reprend le texte de départ. Les destinations des boutons ne se modifient pas ici.',
                'keys' => ['home.eyebrow', 'home.title', 'home.lede', 'home.search_hint', 'home.chips', 'home.mission_prompt', 'home.freelance_title', 'home.freelance_text', 'home.tagline', 'home.mission_btn_label', 'home.mission_btn_dest', 'home.freelance_btn_label', 'home.freelance_btn_dest', 'home.announce_enabled', 'home.announce_text', 'home.announce_link', 'home.announce_link_label']],
            'site' => ['title' => 'Site et sauvegardes', 'approvable' => false,
                'intro' => 'Visibilité du site, courriels de notification et suivi des sauvegardes.',
                'keys' => ['noindex', 'notifications.emails', 'ops.backup_dir', 'ops.backup_max_age_hours']],
        ];
    }

    /**
     * @return array<string, array{label: string, help: string, type: string, min?: int, max?: int, unit?: string, length?: int, secret?: bool, options?: array<string, string>, path?: string}>
     */
    public static function all(): array
    {
        static $all = null;

        return $all ??= self::build();
    }

    /** @return array<string, mixed> */
    private static function build(): array
    {
        $d = [
            'finance.commission_bp' => ['label' => 'Commission FreeCI', 'help' => 'En pourcentage du montant payé par le client (0 à 30 %).', 'type' => 'percent', 'min' => 0, 'max' => 3000, 'unit' => '%'],
            'orders.response_hours' => ['label' => 'Délai de réponse du freelance', 'help' => 'Au-delà, la demande expire.', 'type' => 'int', 'min' => 1, 'max' => 240, 'unit' => 'heures'],
            'orders.payment_hours' => ['label' => 'Délai de paiement du client', 'help' => 'Après acceptation. Au-delà, la commande expire.', 'type' => 'int', 'min' => 1, 'max' => 168, 'unit' => 'heures'],
            'orders.review_days' => ['label' => 'Délai d’examen d’une livraison', 'help' => 'Temps laissé au client pour valider ou demander une correction.', 'type' => 'int', 'min' => 1, 'max' => 60, 'unit' => 'jours'],
            'orders.extension_max_days' => ['label' => 'Report d’échéance maximal', 'help' => 'Durée maximale d’un report demandé par le freelance.', 'type' => 'int', 'min' => 1, 'max' => 90, 'unit' => 'jours'],
            'missions.selection_days' => ['label' => 'Durée de sélection d’une mission', 'help' => 'Temps laissé au client pour choisir une proposition.', 'type' => 'int', 'min' => 1, 'max' => 90, 'unit' => 'jours'],
            'reviews.publication_days' => ['label' => 'Publication des avis', 'help' => 'Délai entre la clôture de la commande et la publication de l’avis. Figé au dépôt de chaque avis.', 'type' => 'int', 'min' => 0, 'max' => 60, 'unit' => 'jours'],
            'account.closure_grace_days' => ['label' => 'Réflexion avant fermeture de compte', 'help' => 'Délai pendant lequel la demande peut être annulée.', 'type' => 'int', 'min' => 0, 'max' => 90, 'unit' => 'jours'],
            'catalog.price_xof.0' => ['label' => 'Prix minimum d’un service', 'help' => 'En FCFA.', 'type' => 'xof', 'min' => 100, 'max' => 5000000, 'unit' => 'FCFA'],
            'catalog.price_xof.1' => ['label' => 'Prix maximum d’un service', 'help' => 'En FCFA.', 'type' => 'xof', 'min' => 100, 'max' => 50000000, 'unit' => 'FCFA'],
            // Paiement
            'payments.mode' => ['label' => 'Mode de paiement', 'help' => 'Sandbox : aucun argent réel. Live : argent réel — exige la saisie de la phrase de confirmation.', 'type' => 'select', 'options' => ['sandbox' => 'Sandbox (test, aucun argent réel)', 'live' => 'Live (argent réel)']],
            'payments.enabled' => ['label' => 'Ouvrir les nouveaux paiements', 'help' => 'Désactivé : aucun nouveau paiement ne peut être créé (le suivi des tentatives existantes continue).', 'type' => 'bool'],
            'payments.live_authorized' => ['label' => 'Autoriser le paiement réel', 'help' => 'Autorisation explicite, distincte du mode : sans elle, le mode live ne crée aucun paiement.', 'type' => 'bool'],
            'payments.genius.base_url' => ['label' => 'Adresse de l’API Genius Pay', 'help' => 'Doit commencer par https://.', 'type' => 'text', 'length' => 200],
            'payments.genius.checkout_hosts' => ['label' => 'Hôtes de paiement autorisés', 'help' => 'Séparés par des virgules. Les clients ne sont redirigés que vers ces hôtes.', 'type' => 'text', 'length' => 200],
            'payments.genius.webhook_tolerance' => ['label' => 'Tolérance des notifications', 'help' => 'Écart d’horloge accepté sur les notifications signées.', 'type' => 'int', 'min' => 30, 'max' => 259200, 'unit' => 's'],
        ];
        foreach (['sandbox' => 'bac à sable', 'live' => 'réel'] as $env => $name) {
            $d["payments.genius.{$env}.api_key"] = ['label' => "Clé publique ({$name})", 'help' => $env === 'live' ? 'Commence par pk_live_.' : 'Commence par pk_sandbox_.', 'type' => 'secret', 'length' => 300];
            $d["payments.genius.{$env}.api_secret"] = ['label' => "Clé secrète ({$name})", 'help' => $env === 'live' ? 'Commence par sk_live_.' : 'Commence par sk_sandbox_.', 'type' => 'secret', 'length' => 300];
            $d["payments.genius.{$env}.webhook_secret"] = ['label' => "Secret du webhook ({$name})", 'help' => 'Commence par whsec_.', 'type' => 'secret', 'length' => 300];
            $d["payments.genius.{$env}.merchant_id"] = ['label' => "Compte marchand ({$name})", 'help' => $env === 'live' ? 'Obligatoire en réel : le compte attendu.' : 'Facultatif en bac à sable.', 'type' => 'text', 'length' => 100];
        }
        $d += [
            'mail.default' => ['label' => 'Envoi des courriels', 'help' => '« Journal » n’envoie rien : les courriels restent dans le journal du serveur.', 'type' => 'select', 'options' => ['smtp' => 'SMTP (envoi réel)', 'log' => 'Journal (aucun envoi)'], 'path' => 'mail.default'],
            'mail.host' => ['label' => 'Serveur SMTP', 'help' => 'Ex. smtp.gmail.com.', 'type' => 'text', 'length' => 200, 'path' => 'mail.mailers.smtp.host'],
            'mail.port' => ['label' => 'Port', 'help' => '587 (STARTTLS) ou 465 (SSL).', 'type' => 'int', 'min' => 1, 'max' => 65535, 'path' => 'mail.mailers.smtp.port'],
            'mail.scheme' => ['label' => 'Chiffrement', 'help' => 'Automatique convient au port 587 ; SSL pour le port 465.', 'type' => 'select', 'options' => ['' => 'Automatique (STARTTLS)', 'smtps' => 'SSL (port 465)'], 'path' => 'mail.mailers.smtp.scheme'],
            'mail.username' => ['label' => 'Identifiant SMTP', 'help' => 'Souvent l’adresse e-mail.', 'type' => 'text', 'length' => 200, 'path' => 'mail.mailers.smtp.username'],
            'mail.password' => ['label' => 'Mot de passe SMTP', 'help' => 'Pour Gmail : un mot de passe d’application.', 'type' => 'secret', 'length' => 300, 'path' => 'mail.mailers.smtp.password'],
            'mail.from_address' => ['label' => 'Adresse d’expédition', 'help' => 'Avec Gmail : l’adresse Gmail elle-même.', 'type' => 'email', 'length' => 254, 'path' => 'mail.from.address'],
            'mail.from_name' => ['label' => 'Nom d’expéditeur', 'help' => 'Affiché dans la boîte du destinataire.', 'type' => 'text', 'length' => 100, 'path' => 'mail.from.name'],
            'files.scanner' => ['label' => 'Contrôle des fichiers', 'help' => 'Sans antivirus, le dépôt de fichiers est désactivé.', 'type' => 'select', 'options' => ['none' => 'Aucun (dépôt désactivé)', 'clamav' => 'ClamAV']],
            'files.clamscan_binary' => ['label' => 'Programme ClamAV', 'help' => 'Chemin sur le serveur.', 'type' => 'text', 'length' => 200],
            'files.max_mb' => ['label' => 'Taille maximale d’un fichier de brief', 'help' => '', 'type' => 'int', 'min' => 1, 'max' => 200, 'unit' => 'Mo'],
            'files.delivery_max_mb' => ['label' => 'Taille maximale d’un fichier de livraison', 'help' => '', 'type' => 'int', 'min' => 1, 'max' => 200, 'unit' => 'Mo'],
            'files.max_files' => ['label' => 'Nombre maximal de fichiers', 'help' => 'Par dépôt.', 'type' => 'int', 'min' => 1, 'max' => 50],
            'files.max_total_mb' => ['label' => 'Taille totale maximale', 'help' => 'Par commande.', 'type' => 'int', 'min' => 1, 'max' => 1000, 'unit' => 'Mo'],
        ];
        // Bornes de rédaction (paires minimum / maximum)
        $pairs = [
            'catalog.title' => ['Titre d’un service', 'caractères', 1, 300], 'catalog.summary' => ['Résumé d’un service', 'caractères', 1, 1000], 'catalog.scope' => ['Périmètre d’un service', 'caractères', 1, 20000],
            'catalog.delivery_days' => ['Délai de livraison d’un service', 'jours', 1, 365], 'catalog.revisions' => ['Corrections incluses', '', 0, 50], 'catalog.bio' => ['Présentation du profil', 'caractères', 1, 10000],
            'catalog.skill' => ['Longueur d’une compétence', 'caractères', 1, 100],
            'missions.title' => ['Titre d’une mission', 'caractères', 1, 300], 'missions.description' => ['Description d’une mission', 'caractères', 1, 20000], 'missions.budget_xof' => ['Budget d’une mission', 'FCFA', 100, 100000000],
            'missions.proposal.price_xof' => ['Prix d’une proposition', 'FCFA', 100, 100000000], 'missions.proposal.delivery_days' => ['Délai d’une proposition', 'jours', 1, 365], 'missions.proposal.revisions' => ['Corrections d’une proposition', '', 0, 50],
            'missions.proposal.scope' => ['Périmètre d’une proposition', 'caractères', 1, 20000], 'missions.proposal.validity_days' => ['Validité d’une proposition', 'jours', 1, 365],
        ];
        foreach ($pairs as $base => [$label, $unit, $lo, $hi]) {
            $d[$base.'.0'] = ['label' => $label.' : minimum', 'help' => '', 'type' => 'int', 'min' => $lo, 'max' => $hi, 'unit' => $unit];
            $d[$base.'.1'] = ['label' => $label.' : maximum', 'help' => '', 'type' => 'int', 'min' => $lo, 'max' => $hi, 'unit' => $unit];
        }
        $ints = [
            'catalog.deliverables_max' => ['Livrables par service', '', 1, 50], 'catalog.exclusions_max' => ['Exclusions par service', '', 1, 50], 'catalog.client_inputs_max' => ['Informations demandées au client', '', 1, 50],
            'catalog.line_max' => ['Longueur d’une ligne de liste', 'caractères', 20, 1000], 'catalog.images_max' => ['Images par service', '', 0, 20], 'catalog.image_max_mb' => ['Poids maximal d’une image', 'Mo', 1, 50],
            'catalog.image_min_width' => ['Largeur minimale d’une image', 'px', 100, 4000], 'catalog.skills_max' => ['Compétences par profil', '', 1, 50],
            'missions.deadline_max_days' => ['Date limite de candidature : maximum', 'jours', 1, 365], 'missions.client_inputs_max' => ['Informations demandées (missions)', '', 1, 50], 'missions.line_max' => ['Longueur d’une ligne (missions)', 'caractères', 20, 1000],
            'missions.proposal.deliverables_max' => ['Livrables par proposition', '', 1, 50],
            'reviews.comment_min' => ['Avis : commentaire minimum', 'caractères', 0, 500], 'reviews.comment_max' => ['Avis : commentaire maximum', 'caractères', 50, 10000], 'reviews.reply_max' => ['Réponse à un avis : maximum', 'caractères', 50, 10000],
            'reviews.per_page' => ['Avis par page', '', 5, 100], 'messaging.body_max' => ['Message : longueur maximale', 'caractères', 100, 20000], 'messaging.per_10_minutes' => ['Messages par 10 minutes', '', 5, 500],
            'support.body_max' => ['Assistance : longueur maximale', 'caractères', 100, 20000], 'support.per_day' => ['Dossiers d’assistance par jour', '', 1, 100], 'support.messages_per_hour' => ['Messages d’assistance par heure', '', 5, 500], 'support.max_files' => ['Fichiers par dossier d’assistance', '', 0, 100],
            'admin.mfa_session_minutes' => ['Validité de la double authentification', 'minutes', 15, 1440], 'admin.reauth_minutes' => ['Validité de la confirmation d’identité', 'minutes', 1, 120],
            'admin.mfa_attempts' => ['Tentatives de code avant blocage', '', 3, 20], 'admin.mfa_lock_minutes' => ['Durée de blocage', 'minutes', 1, 1440], 'admin.page_size' => ['Lignes par page (administration)', '', 10, 200],
            'account.export_per_day' => ['Exports de données par jour', '', 1, 50], 'account.email_change_minutes' => ['Validité du lien de changement d’adresse', 'minutes', 10, 1440],
            'ops.backup_max_age_hours' => ['Âge maximal d’une sauvegarde « récente »', 'heures', 1, 720],
        ];
        foreach ($ints as $k => [$label, $unit, $lo, $hi]) {
            $d[$k] = ['label' => $label, 'help' => '', 'type' => 'int', 'min' => $lo, 'max' => $hi, 'unit' => $unit];
        }
        $d += [
            'legal.operator_name' => ['label' => 'Nom de l’exploitant', 'help' => 'Raison sociale ou nom.', 'type' => 'text', 'length' => 120],
            'legal.operator_address' => ['label' => 'Adresse', 'help' => 'Adresse postale.', 'type' => 'text', 'length' => 300],
            'legal.operator_registration' => ['label' => 'Immatriculation', 'help' => 'Numéro d’immatriculation (RCCM, etc.).', 'type' => 'text', 'length' => 120],
            'legal.publication_director' => ['label' => 'Directeur de la publication', 'help' => 'Nom.', 'type' => 'text', 'length' => 120],
            'legal.host' => ['label' => 'Hébergeur', 'help' => 'Nom et coordonnées de l’hébergeur.', 'type' => 'text', 'length' => 200],
            'legal.contact_email' => ['label' => 'Adresse de contact', 'help' => 'Adresse publique de contact.', 'type' => 'email', 'length' => 254],
            'mailtpl.subject_prefix' => ['label' => 'Début de l’objet des courriels', 'help' => 'Ex. « FreeCI » : l’objet devient « FreeCI : Livraison à examiner ».', 'type' => 'text', 'length' => 40],
            'mailtpl.greeting' => ['label' => 'Formule d’ouverture', 'help' => 'Ex. « Bonjour, ».', 'type' => 'text', 'length' => 60],
            'mailtpl.signature' => ['label' => 'Signature', 'help' => 'Dernière ligne des courriels.', 'type' => 'text', 'length' => 80],
            'mailtpl.verify_intro' => ['label' => 'Courriel de confirmation d’adresse : phrase avant le lien', 'help' => 'Le lien et sa durée de validité (60 minutes) sont ajoutés automatiquement.', 'type' => 'text', 'length' => 240],
            'mailtpl.verify_ignore' => ['label' => 'Courriel de confirmation d’adresse : phrase finale', 'help' => 'Pour la personne qui n’a rien demandé.', 'type' => 'text', 'length' => 240],
            'mailtpl.notification_intro' => ['label' => 'Courriel de notification : phrase avant le lien', 'help' => 'Le titre de la notification et le lien sont ajoutés automatiquement.', 'type' => 'text', 'length' => 240],
            'mailtpl.notification_note' => ['label' => 'Courriel de notification : note finale', 'help' => 'Aucun message privé ni pièce jointe n’est jamais envoyé par courriel.', 'type' => 'text', 'length' => 300],
            'mailtpl.account_warning' => ['label' => 'Avis de sécurité du compte : consigne finale', 'help' => 'Pour les changements d’adresse et les fermetures de compte.', 'type' => 'text', 'length' => 300],
            'support.subjects.order' => ['label' => 'Assistance : sujet « commande »', 'help' => 'Libellé proposé dans le formulaire de contact. Vide : « Une commande ».', 'type' => 'text', 'length' => 50],
            'support.subjects.account' => ['label' => 'Assistance : sujet « compte »', 'help' => 'Vide : « Mon compte ».', 'type' => 'text', 'length' => 50],
            'support.subjects.payment' => ['label' => 'Assistance : sujet « paiement »', 'help' => 'Vide : « Un paiement ».', 'type' => 'text', 'length' => 50],
            'support.subjects.technical' => ['label' => 'Assistance : sujet « problème technique »', 'help' => 'Vide : « Un problème technique ».', 'type' => 'text', 'length' => 50],
            'support.subjects.other' => ['label' => 'Assistance : sujet « autre »', 'help' => 'Vide : « Autre ».', 'type' => 'text', 'length' => 50],
            'home.eyebrow' => ['label' => 'Accroche au-dessus du titre', 'help' => 'Petite ligne en majuscules, en haut de l’accueil.', 'type' => 'text', 'length' => 80],
            'home.title' => ['label' => 'Titre de l’accueil', 'help' => 'La phrase principale.', 'type' => 'text', 'length' => 90],
            'home.lede' => ['label' => 'Sous-titre de l’accueil', 'help' => 'Une ou deux phrases sous le titre.', 'type' => 'text', 'length' => 220],
            'home.search_hint' => ['label' => 'Exemple dans la zone de recherche', 'help' => 'Texte grisé de la recherche.', 'type' => 'text', 'length' => 60],
            'home.chips' => ['label' => 'Recherches fréquentes', 'help' => 'Jusqu’à six termes séparés par des virgules.', 'type' => 'text', 'length' => 160],
            'home.mission_prompt' => ['label' => 'Phrase avant « Publier une mission »', 'help' => 'Courte phrase d’invitation.', 'type' => 'text', 'length' => 60],
            'home.freelance_title' => ['label' => 'Titre de la bande freelance', 'help' => 'Bande en bas de l’accueil.', 'type' => 'text', 'length' => 80],
            'home.freelance_text' => ['label' => 'Texte de la bande freelance', 'help' => 'Une phrase.', 'type' => 'text', 'length' => 160],
            'home.tagline' => ['label' => 'Phrase du pied de page', 'help' => 'Sous le logo, dans le pied de page de tout le site.', 'type' => 'text', 'length' => 200],
            'home.mission_btn_label' => ['label' => 'Bouton « mission » de l’en-tête : texte', 'help' => 'Le second bouton, sous la recherche.', 'type' => 'text', 'length' => 40],
            'home.mission_btn_dest' => ['label' => 'Bouton « mission » de l’en-tête : mène vers', 'help' => 'Une page du site.', 'type' => 'select', 'options' => Destinations::options()],
            'home.freelance_btn_label' => ['label' => 'Bouton de la bande freelance : texte', 'help' => 'Bouton de la bande en bas de l’accueil.', 'type' => 'text', 'length' => 40],
            'home.freelance_btn_dest' => ['label' => 'Bouton de la bande freelance : mène vers', 'help' => 'Une page du site.', 'type' => 'select', 'options' => Destinations::options()],
            'home.announce_enabled' => ['label' => 'Afficher un bandeau d’annonce sur l’accueil', 'help' => 'Une ligne d’information sous l’en-tête (nouveauté, période particulière, rappel). Désactivé : aucun bandeau.', 'type' => 'bool'],
            'home.announce_text' => ['label' => 'Texte du bandeau d’annonce', 'help' => 'Une phrase courte.', 'type' => 'text', 'length' => 160],
            'home.announce_link' => ['label' => 'Où mène le bouton du bandeau', 'help' => 'Choisissez une page du site ; aucun lien extérieur n’est possible ici.', 'type' => 'select', 'options' => ['' => 'Aucun bouton'] + Destinations::options()],
            'home.announce_link_label' => ['label' => 'Texte du bouton du bandeau', 'help' => 'Ex. « Voir le catalogue ».', 'type' => 'text', 'length' => 40],
            'noindex' => ['label' => 'Masquer le site aux moteurs de recherche', 'help' => 'Activé : les moteurs de recherche n’indexent pas le site. À désactiver à l’ouverture au public.', 'type' => 'bool'],
            'notifications.emails' => ['label' => 'Envoyer les notifications par courriel', 'help' => 'Sans courrier configuré, aucun courriel n’est envoyé quoi qu’il arrive.', 'type' => 'bool'],
            'ops.backup_dir' => ['label' => 'Dossier des sauvegardes', 'help' => 'Dossier où le script de sauvegarde écrit son état.', 'type' => 'text', 'length' => 200],
        ];
        foreach ($d as $k => &$def) {
            $def['path'] ??= 'freeci.'.$k;
            $def['secret'] = ($def['type'] === 'secret');
        }

        return $d;
    }

    /** Valeur ramenée à son type (les valeurs du .env sont des chaînes) pour comparer et afficher sans faux changement. */
    public static function normalize(string $key, mixed $v): mixed
    {
        return match (self::all()[$key]['type'] ?? 'text') {
            'bool' => (bool) $v,
            'int', 'xof', 'percent' => is_numeric($v) ? (int) $v : $v,
            'select' => (string) ($v ?? ''),
            'secret' => $v,
            default => ($v === null || $v === '') ? null : (string) $v,
        };
    }

    public static function group(string $key): ?string
    {
        foreach (self::groups() as $g => $d) {
            if (in_array($key, $d['keys'], true)) {
                return $g;
            }
        }

        return null;
    }
}
