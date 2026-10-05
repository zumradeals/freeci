<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\GetFreelanceProfile;
use App\Modules\Accounts\Actions\SaveFreelanceProfile;
use App\Modules\Catalog\Actions\ListFreelancerServices;
use App\Modules\Orders\Queries\FreelancerOverview;
use App\Modules\Orders\Queries\ListOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Espace freelance minimal : demandes reçues, commandes, services (lecture seule), profil. */
class FreelanceController extends Controller
{
    public function dashboard(Request $request, FreelancerOverview $overview, ListFreelancerServices $services, GetFreelanceProfile $profile): View
    {
        return view('freelance.dashboard', [
            'user' => $request->user(), 'o' => $overview($request->user()),
            'services' => $services($request->user()), 'profile' => $profile($request->user()), 'space' => 'freelancer',
        ]);
    }

    public function orders(Request $request, ListOrders $list): View
    {
        return view('orders.index', ['orders' => $list($request->user(), 'freelancer'), 'space' => 'freelancer']);
    }

    public function services(Request $request, ListFreelancerServices $services): View
    {
        return view('freelance.services', ['services' => $services($request->user()), 'space' => 'freelancer']);
    }

    public function profile(Request $request, GetFreelanceProfile $profile): View
    {
        return view('freelance.profile', ['profile' => $profile($request->user()), 'user' => $request->user(), 'activation' => false, 'space' => 'freelancer']);
    }

    public function activate(Request $request, GetFreelanceProfile $profile): View|RedirectResponse
    {
        if ($request->user()->hasRole('freelance')) {
            return redirect()->route('freelance.dashboard');
        }

        return view('freelance.profile', ['profile' => $profile($request->user()), 'user' => $request->user(), 'activation' => true, 'space' => 'client']);
    }

    public function save(Request $request, SaveFreelanceProfile $save): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'], 'headline' => ['required', 'string', 'max:160'], 'city' => ['nullable', 'string', 'max:80'],
        ]);
        $first = ! $request->user()->hasRole('freelance');
        $save($request->user(), $data['display_name'], $data['headline'], $data['city'] ?? null);

        return redirect()->route('freelance.dashboard')->with('status', $first ? 'Espace freelance activé. Votre profil est enregistré.' : 'Profil enregistré.');
    }
}
