<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Pages d'information du pied de page. Une page n'est présentée comme « adoptée » que si son slug figure dans FREECI_PAGES_APPROVED
 * (décision du porteur) ; sinon elle porte un bandeau de BROUILLON et n'est pas indexable. Aucune identité d'exploitant, coordonnée,
 * garantie ou texte juridique n'est inventé : une information absente de la configuration s'affiche « à renseigner ».
 */
class InfoPageController extends Controller
{
    public const PAGES = [
        'fonctionnement' => 'Comment fonctionne FreeCI',
        'aide' => 'Aide',
        'contact' => 'Contact',
        'conditions' => 'Conditions d’utilisation',
        'confidentialite' => 'Confidentialité',
        'mentions-legales' => 'Mentions légales',
    ];

    public function __invoke(string $page): View
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        return view('info.'.$page, ['title' => self::PAGES[$page], 'slug' => $page, 'approved' => in_array($page, config('freeci.legal.approved'), true), 'legal' => config('freeci.legal')]);
    }
}
