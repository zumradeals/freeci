<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\GetFreelanceProfile;
use App\Modules\Accounts\Actions\PublishFreelanceProfile;
use App\Modules\Accounts\Actions\SaveFreelanceProfile;
use App\Modules\Catalog\Actions\AvailabilityManager;
use App\Modules\Catalog\Actions\ListFreelancerServices;
use App\Modules\Finance\Queries\FreelancerEarnings;
use App\Modules\Missions\Queries\MissionInvitationQueries;
use App\Modules\Missions\Queries\RecommendedMissions;
use App\Modules\Orders\Queries\FreelancerOverview;
use App\Modules\Orders\Queries\ListOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Espace freelance minimal : demandes reçues, commandes, services (lecture seule), profil. */
class FreelanceController extends Controller
{
    public function dashboard(Request $request, FreelancerOverview $overview, ListFreelancerServices $services, GetFreelanceProfile $profile, FreelancerEarnings $earnings): View
    {
        return view('freelance.dashboard', [
            'user' => $request->user(), 'o' => $overview($request->user()),
            'services' => $services($request->user()), 'profile' => $profile($request->user()), 'availability' => app(AvailabilityManager::class)->state($request->user()), 'revenue' => $earnings->overview($request->user(), 1)['totals']['real'], 'recommended' => app(RecommendedMissions::class)->for($request->user(), 3), 'invitationsPending' => app(MissionInvitationQueries::class)->pendingCount($request->user()), 'space' => 'freelancer',
        ]);
    }

    public function orders(Request $request, ListOrders $list): View
    {
        return view('orders.index', ['orders' => $list($request->user(), 'freelancer'), 'space' => 'freelancer']);
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
            'bio' => ['nullable', 'string', 'max:3000'], 'skills' => ['nullable', 'string', 'max:600'],
        ]);      // liste blanche : tout autre champ envoyé (badge, vérification, rôle, publication…) est ignoré
        $first = ! $request->user()->hasRole('freelance');
        $save($request->user(), $data['display_name'], $data['headline'], $data['city'] ?? null, $request->has('bio') ? (string) ($data['bio'] ?? '') : null, $request->has('skills') ? (string) ($data['skills'] ?? '') : null);

        return $first ? redirect()->route('freelance.dashboard')->with('status', 'Espace freelance activé. Votre profil est enregistré.')
            : redirect()->route('freelance.profile')->with('status', 'Profil enregistré.');
    }

    public function publish(Request $request, PublishFreelanceProfile $publish): RedirectResponse
    {
        $publish($request->user());

        return redirect()->route('freelance.profile')->with('status', 'Profil publié : il est visible sur votre page publique.');
    }
}
