<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Fonctions hors du lot en cours : annoncées honnêtement, sans formulaire ni faux effet. */
class ComingSoonController extends Controller
{
    public const FEATURES = [
        'missions' => ['Missions', 'Publier une mission et comparer des propositions.'],
        'freelances' => ['Freelances', 'Parcourir les profils des freelances.'],
        'publier-une-mission' => ['Publier une mission', 'Décrire un besoin et recevoir des propositions chiffrées.'],
        'creer-un-profil' => ['Créer un profil freelance', 'Présenter vos compétences et publier vos prestations.'],
        'commandes' => ['Commandes', 'Suivre vos commandes, de la demande à la validation.'],
        'messages' => ['Messages', 'Échanger avec un freelance ou un client.'],
        'paiements' => ['Paiements', 'Consulter votre situation financière.'],
        'favoris' => ['Favoris', 'Retrouver les services que vous avez enregistrés.'],
        'compte' => ['Compte', 'Gérer vos informations personnelles.'],
        'aide' => ['Aide', 'Centre d’aide et contact du support.'],
        'conditions' => ['Conditions d’utilisation', 'Texte juridique à rédiger.'],
        'confidentialite' => ['Confidentialité', 'Texte juridique à rédiger.'],
        'mentions-legales' => ['Mentions légales', 'Mentions de l’opérateur à fournir.'],
    ];

    public function __invoke(string $feature): View
    {
        abort_unless(isset(self::FEATURES[$feature]), 404);

        [$title, $text] = self::FEATURES[$feature];

        return view('pages.coming-soon', ['title' => $title, 'text' => $text]);
    }
}
