<?php

namespace App\Http\Controllers;

use App\Modules\Admin\Legal\LegalDefaults;
use App\Modules\Admin\Legal\LegalPages;
use Illuminate\View\View;

/**
 * Pages d'information du pied de page. Le texte et son statut viennent de l'administration (Pages légales) : une page n'est présentée comme adoptée
 * que lorsque l'administrateur l'a publiée ; sinon le texte de départ s'affiche avec un bandeau « brouillon » et n'est pas indexable.
 */
class InfoPageController extends Controller
{
    public const PAGES = LegalDefaults::PAGES;

    public function __invoke(string $page): View
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        return view('info.page', ['title' => self::PAGES[$page], 'slug' => $page, 'approved' => LegalPages::adopted($page), 'html' => LegalPages::render(LegalPages::publicBody($page))]);
    }
}
