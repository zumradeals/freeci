<?php

namespace App\Http\Controllers;

use App\Modules\Accounts\Security\SecondFactorGate;
use App\Modules\Finance\Actions\Beneficiaries;
use App\Modules\Finance\Queries\ClientFinance;
use App\Modules\Finance\Queries\FreelancerEarnings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pages financières des PARTIES (lecture seule, bornées à leurs propres commandes) : état financier client, revenus freelance, destination de reversement. */
class FinanceSpaceController extends Controller
{
    public function client(Request $request, ClientFinance $q): View
    {
        return view('account.finances', $q->overview($request->user(), max(1, $request->integer('page', 1))) + ['space' => 'client']);
    }

    public function freelancer(Request $request, FreelancerEarnings $q): View
    {
        return view('freelance.earnings', ['d' => $q->overview($request->user(), max(1, $request->integer('page', 1))), 'space' => 'freelancer']);
    }

    public function declareBeneficiary(Request $request, Beneficiaries $b): RedirectResponse
    {
        $d = $request->validate(['method' => ['required', 'in:mobile_money,bank_transfer,other'], 'holder' => ['required', 'string', 'min:2', 'max:120'], 'destination' => ['required', 'string', 'min:6', 'max:120'], 'code' => ['nullable', 'string', 'max:20']]);
        app(SecondFactorGate::class)->assert($request->user(), $d['code'] ?? null);          // double authentification active : un code est demandé pour changer l'endroit où l'argent est versé
        try {
            $b->declare($request->user(), $d['method'], $d['holder'], $d['destination']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Coordonnées enregistrées. L’administrateur doit les vérifier avant tout versement. Aucun transfert n’a été effectué.');
    }
}
