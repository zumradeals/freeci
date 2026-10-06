<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Legal\LegalDefaults;
use App\Modules\Admin\Legal\LegalPages;
use App\Modules\Admin\Queries\SettingsOverview;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pages d'information et textes légaux : brouillon, aperçu, publication comme texte adopté, retrait. */
class LegalPagesController extends Controller
{
    public function index(SettingsOverview $overview): View
    {
        return view('admin.legal.index', ['pages' => $overview->legalPages()]);
    }

    public function edit(SettingsOverview $overview, string $slug): View
    {
        abort_unless(isset(LegalDefaults::PAGES[$slug]), 404);
        $body = LegalPages::editableBody($slug);

        return view('admin.legal.edit', ['slug' => $slug, 'title' => LegalDefaults::PAGES[$slug], 'state' => LegalPages::state($slug), 'body' => old('body', $body), 'html' => LegalPages::render($body),
            'history' => $overview->legalHistory($slug), 'row' => LegalPages::row($slug)]);
    }

    public function draft(Request $request, LegalPages $pages, string $slug): RedirectResponse
    {
        abort_unless(isset(LegalDefaults::PAGES[$slug]), 404);
        $d = $request->validate(['body' => ['required', 'string', 'max:'.LegalPages::MAX]]);
        try {
            $pages->saveDraft($request->user(), $slug, $d['body']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Brouillon enregistré : il n’est pas public. Relisez l’aperçu, puis publiez-le comme texte adopté.');
    }

    public function publish(Request $request, LegalPages $pages, string $slug): RedirectResponse
    {
        abort_unless(isset(LegalDefaults::PAGES[$slug]), 404);
        $d = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Cochez la case pour confirmer que ce texte est adopté.']);
        try {
            $v = $pages->publish($request->user(), $slug, $d['reason']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Texte publié comme adopté (version {$v}) : le bandeau « brouillon » disparaît de cette page.");
    }

    public function withdraw(Request $request, LegalPages $pages, string $slug): RedirectResponse
    {
        abort_unless(isset(LegalDefaults::PAGES[$slug]), 404);
        $d = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            $pages->withdraw($request->user(), $slug, $d['reason']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Adoption retirée : la page redevient un brouillon public. Le texte et l’historique sont conservés.');
    }
}
