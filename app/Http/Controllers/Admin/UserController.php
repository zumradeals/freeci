<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\ManageAccounts;
use App\Modules\Admin\Actions\RemovePortfolioItem;
use App\Modules\Admin\Actions\RemoveProfilePhoto;
use App\Modules\Admin\Actions\ResetUserTwoFactor;
use App\Modules\Admin\Queries\UsersQuery;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, UsersQuery $users): View
    {
        $f = ['q' => (string) $request->query('q', ''), 'status' => (string) $request->query('statut', ''), 'role' => (string) $request->query('role', '')];

        return view('admin.users.index', ['page' => $users->search($f, (int) config('freeci.admin.page_size')), 'f' => $f, 'counts' => $users->counts()]);
    }

    public function show(string $id, UsersQuery $users): View
    {
        $d = $users->detail($id);
        abort_if($d === null, 404);

        return view('admin.users.show', ['u' => $d]);
    }

    /** Suspension / réactivation (sensibles : confirmation récente exigée par la route). */
    public function change(Request $request, string $id, string $action, ManageAccounts $accounts): RedirectResponse
    {
        abort_unless(in_array($action, ['suspendre', 'reactiver'], true), 404);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'];
        try {
            $action === 'suspendre' ? $accounts->suspend($request->user(), $id, $reason) : $accounts->reactivate($request->user(), $id, $reason);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.users.show', $id)->with('status', $action === 'suspendre'
            ? 'Compte suspendu : plus de nouvelle activité. Ses commandes et obligations en cours ne sont pas affectées.' : 'Compte réactivé.');
    }

    /** Retrait de la photo de profil (sensible : confirmation récente exigée par la route ; motif obligatoire, journal d'audit). */
    public function removePhoto(Request $request, string $id, RemoveProfilePhoto $remove): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'];
        try {
            $remove($request->user(), $id, $reason);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.users.show', $id)->with('status', 'Photo retirée : la personne en est informée avec le motif.');
    }

    /** Réinitialisation de la double authentification d'une personne (sensible : confirmation récente exigée par la route ; motif obligatoire, journal d'audit, notification). */
    public function resetTwoFactor(Request $request, string $id, ResetUserTwoFactor $reset): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'];
        try {
            $reset($request->user(), $id, $reason);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.users.show', $id)->with('status', 'Double authentification réinitialisée : les sessions de la personne sont fermées et elle en est informée avec le motif.');
    }

    /** Retrait d'une réalisation du portfolio (mêmes garde-fous que la photo : confirmation récente, motif, journal d'audit). */
    public function removePortfolio(Request $request, string $id, string $item, RemovePortfolioItem $remove): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'];
        try {
            $remove($request->user(), $id, $item, $reason);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.users.show', $id)->with('status', 'Réalisation retirée : la personne en est informée avec le motif.');
    }
}
