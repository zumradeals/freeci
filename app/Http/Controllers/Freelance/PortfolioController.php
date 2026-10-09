<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\Portfolio;
use App\Modules\Files\Exceptions\FileRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Réalisations du freelance : ajout, modification des textes, suppression. Publiques dès l'enregistrement (profil publié). */
class PortfolioController extends Controller
{
    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file']], ['image.required' => 'Choisissez une image.']);
        try {
            $portfolio->add($request->user(), $request->file('image'), (string) $request->input('title'), $request->input('description'), $request->input('year'));
        } catch (FileRejected $e) {
            return redirect()->to(route('freelance.profile').'#h-po')->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(route('freelance.profile').'#h-po')->with('status', 'Réalisation ajoutée. Elle est visible dès maintenant sur votre profil public.');
    }

    public function update(Request $request, Portfolio $portfolio, string $id): RedirectResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $id) === 1, 404);
        try {
            $portfolio->update($request->user(), $id, (string) $request->input('title'), $request->input('description'), $request->input('year'));
        } catch (FileRejected $e) {
            return redirect()->to(route('freelance.profile').'#h-po')->with('error', $e->getMessage());
        }

        return redirect()->to(route('freelance.profile').'#h-po')->with('status', 'Réalisation modifiée.');
    }

    public function destroy(Request $request, Portfolio $portfolio, string $id): RedirectResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $id) === 1, 404);
        $done = $portfolio->delete($request->user(), $id);

        return redirect()->to(route('freelance.profile').'#h-po')->with('status', $done ? 'Réalisation supprimée.' : 'Cette réalisation n’existe plus.');
    }
}
