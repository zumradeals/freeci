<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\ManageCategories;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Catégories de services et de missions : création, nom, icône, ordre, archivage, suppression si inutilisée. */
class CategoriesController extends Controller
{
    public function index(ManageCategories $categories): View
    {
        return view('admin.categories', ['rows' => $categories->overview(), 'icons' => ManageCategories::ICONS]);
    }

    public function store(Request $request, ManageCategories $categories): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'icon' => ['required', 'string', 'max:30']]);

        return $this->attempt(fn () => $categories->create($request->user(), $d['name'], $d['icon']), 'Catégorie créée : elle est proposée dès maintenant aux freelances et aux clients.');
    }

    public function update(Request $request, ManageCategories $categories, string $id): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'icon' => ['required', 'string', 'max:30']]);

        return $this->attempt(fn () => $categories->update($request->user(), $id, $d['name'], $d['icon']), 'Catégorie enregistrée.');
    }

    public function move(Request $request, ManageCategories $categories, string $id, string $direction): RedirectResponse
    {
        return $this->attempt(fn () => $categories->move($request->user(), $id, $direction), 'Ordre modifié.');
    }

    public function feature(Request $request, ManageCategories $categories, string $id): RedirectResponse
    {
        return $this->attempt(fn () => $categories->feature($request->user(), $id), 'Catégorie mise en avant sur l’accueil.');
    }

    public function unfeature(Request $request, ManageCategories $categories, string $id): RedirectResponse
    {
        return $this->attempt(fn () => $categories->unfeature($request->user(), $id), 'Mise en avant retirée.');
    }

    public function archive(Request $request, ManageCategories $categories, string $id): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->attempt(fn () => $categories->archive($request->user(), $id, $d['reason']), 'Catégorie archivée : elle n’est plus proposée ; les services et missions existants sont conservés.');
    }

    public function restore(Request $request, ManageCategories $categories, string $id): RedirectResponse
    {
        return $this->attempt(fn () => $categories->restore($request->user(), $id), 'Catégorie rétablie (placée en dernier).');
    }

    public function destroy(Request $request, ManageCategories $categories, string $id): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->attempt(fn () => $categories->delete($request->user(), $id, $d['reason']), 'Catégorie supprimée.');
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
