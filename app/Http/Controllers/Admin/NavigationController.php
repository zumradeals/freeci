<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\ManageNavigation;
use App\Modules\Admin\Navigation\Destinations;
use App\Modules\Admin\Navigation\MenuItems;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Menus du site : en-tête et pied de page, libellés, ordre, visibilité et destination (choisie dans une liste de pages). */
class NavigationController extends Controller
{
    public function index(): View
    {
        $areas = [];
        foreach (MenuItems::AREAS as $area => $title) {
            $areas[$area] = ['title' => $title, 'customized' => MenuItems::isCustomized($area), 'items' => MenuItems::editable($area), 'max' => MenuItems::MAX[$area]];
        }

        return view('admin.navigation', ['areas' => $areas, 'destinations' => Destinations::options()]);
    }

    public function customize(Request $request, ManageNavigation $nav, string $area): RedirectResponse
    {
        return $this->attempt(fn () => $nav->customize($request->user(), $area), 'Menu personnalisable : modifiez les liens ci-dessous.');
    }

    public function add(Request $request, ManageNavigation $nav, string $area): RedirectResponse
    {
        $d = $request->validate(['label' => ['required', 'string', 'max:100'], 'destination' => ['required', 'string', 'max:30']]);

        return $this->attempt(fn () => $nav->add($request->user(), $area, $d['label'], $d['destination']), 'Lien ajouté.');
    }

    public function update(Request $request, ManageNavigation $nav, int $id): RedirectResponse
    {
        $d = $request->validate(['label' => ['required', 'string', 'max:100'], 'destination' => ['required', 'string', 'max:30']]);

        return $this->attempt(fn () => $nav->update($request->user(), $id, $d['label'], $d['destination'], $request->boolean('visible')), 'Lien enregistré.');
    }

    public function move(Request $request, ManageNavigation $nav, int $id, string $direction): RedirectResponse
    {
        return $this->attempt(fn () => $nav->move($request->user(), $id, $direction), 'Ordre modifié.');
    }

    public function delete(Request $request, ManageNavigation $nav, int $id): RedirectResponse
    {
        return $this->attempt(fn () => $nav->delete($request->user(), $id), 'Lien supprimé.');
    }

    public function reset(Request $request, ManageNavigation $nav, string $area): RedirectResponse
    {
        return $this->attempt(fn () => $nav->reset($request->user(), $area), 'Menu d’origine rétabli.');
    }

    private function attempt(callable $do, string $ok): RedirectResponse
    {
        try {
            $do();
        } catch (ModerationDenied|\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', $ok);
    }
}
