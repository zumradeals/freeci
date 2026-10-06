<?php

namespace App\Modules\Admin\Settings;

/**
 * Registre des paramètres administrables. Chaque paramètre vise un chemin de `config('freeci.…')` : le code existant le lit inchangé, l'administration
 * ne fait que SUPERPOSER une valeur saisie (avec statut provisoire / approuvé, auteur, motif, historique). Sans valeur saisie, la valeur par défaut
 * (code ou .env) s'applique. Les secrets (SMTP, clés Genius Pay, APP_KEY) n'en font volontairement pas partie.
 */
final class SettingDefinitions
{
    /** @return array<string, array{title: string, intro: string, financial?: bool, approvable: bool, keys: list<string>}> */
    public static function groups(): array
    {
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
            'exploitant' => ['title' => 'Exploitant', 'approvable' => false,
                'intro' => 'Identité et coordonnées affichées dans les mentions légales, la confidentialité et la page contact. Rien n’est affiché tant que ce n’est pas renseigné.',
                'keys' => ['legal.operator_name', 'legal.operator_address', 'legal.operator_registration', 'legal.publication_director', 'legal.host', 'legal.contact_email']],
            'site' => ['title' => 'Site', 'approvable' => false,
                'intro' => 'Visibilité du site et courriels de notification.',
                'keys' => ['noindex', 'notifications.emails']],
        ];
    }

    /** @return array<string, array{label: string, help: string, type: 'percent'|'int'|'xof'|'text'|'email'|'bool', min?: int, max?: int, unit?: string, length?: int}> */
    public static function all(): array
    {
        return [
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
            'legal.operator_name' => ['label' => 'Nom de l’exploitant', 'help' => 'Raison sociale ou nom.', 'type' => 'text', 'length' => 120],
            'legal.operator_address' => ['label' => 'Adresse', 'help' => 'Adresse postale.', 'type' => 'text', 'length' => 300],
            'legal.operator_registration' => ['label' => 'Immatriculation', 'help' => 'Numéro d’immatriculation (RCCM, etc.).', 'type' => 'text', 'length' => 120],
            'legal.publication_director' => ['label' => 'Directeur de la publication', 'help' => 'Nom.', 'type' => 'text', 'length' => 120],
            'legal.host' => ['label' => 'Hébergeur', 'help' => 'Nom et coordonnées de l’hébergeur.', 'type' => 'text', 'length' => 200],
            'legal.contact_email' => ['label' => 'Adresse de contact', 'help' => 'Adresse publique de contact.', 'type' => 'email', 'length' => 254],
            'noindex' => ['label' => 'Masquer le site aux moteurs de recherche', 'help' => 'Activé : les moteurs de recherche n’indexent pas le site. À désactiver à l’ouverture au public.', 'type' => 'bool'],
            'notifications.emails' => ['label' => 'Envoyer les notifications par courriel', 'help' => 'Sans courrier configuré sur le serveur, aucun courriel n’est envoyé quoi qu’il arrive.', 'type' => 'bool'],
        ];
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
