<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\AvailabilityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Disponibilité et réactivité du freelance (F-10) : son choix, sa page ; la réactivité est calculée, jamais saisie. */
class AvailabilityController extends Controller
{
    public function show(Request $request, AvailabilityManager $availability): View
    {
        return view('freelance.availability', ['a' => $availability->state($request->user()), 'space' => 'freelancer']);
    }

    public function save(Request $request, AvailabilityManager $availability): RedirectResponse
    {
        $d = $request->validate(['available' => ['required', 'in:yes,no'], 'back_on' => ['nullable', 'string', 'max:10'], 'auto_reopen' => ['nullable', 'boolean']]);
        $availability->set($request->user(), $d['available'] === 'no', $d['back_on'] ?? null, (bool) ($d['auto_reopen'] ?? false));

        return redirect()->route('freelance.availability')->with('status', $d['available'] === 'no'
            ? 'Vous êtes indisponible : plus aucune nouvelle demande ne vous parvient. Vos commandes en cours ne changent pas.'
            : 'Vous êtes de nouveau disponible pour de nouvelles demandes.');
    }
}
