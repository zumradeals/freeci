<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Referrals\Referrals;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Page « Parrainage » d'un compte (F-14) : son code, son lien, ses filleuls (prénom et initiale) et ses commandes à commission offerte. */
class ReferralController extends Controller
{
    public function show(Request $request, Referrals $referrals): View
    {
        $u = $request->user();
        abort_unless(config('freeci.referral.enabled'), 404);

        return view('account.referral', ['r' => $referrals->overview($u), 'space' => $u->hasRole('freelance') && $request->query('espace') === 'freelance' ? 'freelancer' : 'client']);
    }
}
