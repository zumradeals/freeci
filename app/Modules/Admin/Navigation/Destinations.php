<?php

namespace App\Modules\Admin\Navigation;

/**
 * Pages du site vers lesquelles un menu ou un bouton peut mener. Liste FERMÉE : aucun lien libre (ni adresse extérieure, ni javascript:),
 * l'administrateur choisit une page dans une liste. Un clé inconnue ne mène nulle part.
 */
final class Destinations
{
    /** @return array<string, string> clé => libellé de la page, tel qu'affiché dans les listes de l'administration */
    public static function options(): array
    {
        return [
            'home' => 'Accueil', 'services' => 'Catalogue des services', 'missions' => 'Missions ouvertes', 'freelances' => 'Annuaire des freelances',
            'mission_new' => 'Publier une mission', 'freelance_activate' => 'Devenir freelance', 'register' => 'Créer un compte', 'login' => 'Connexion',
            'how' => 'Page « Comment ça marche »', 'help' => 'Page « Centre d’aide »', 'contact' => 'Page « Contact »',
            'terms' => 'Conditions d’utilisation', 'privacy' => 'Confidentialité', 'legal' => 'Mentions légales',
        ];
    }

    public static function url(?string $key): ?string
    {
        return match ($key) {
            'home' => route('home'), 'services' => route('services.index'), 'missions' => route('missions.index'), 'freelances' => route('freelances.index'),
            'mission_new' => route('client.missions.new'), 'freelance_activate' => route('freelance.activate'), 'register' => route('register'), 'login' => route('login'),
            'how' => route('info', 'fonctionnement'), 'help' => route('info', 'aide'), 'contact' => route('info', 'contact'),
            'terms' => route('info', 'conditions'), 'privacy' => route('info', 'confidentialite'), 'legal' => route('info', 'mentions-legales'),
            default => null,
        };
    }
}
