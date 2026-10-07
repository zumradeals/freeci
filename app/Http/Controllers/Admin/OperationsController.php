<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\InstallDemoData;
use App\Modules\Admin\Actions\PurgeDemoData;
use App\Modules\Admin\Queries\OperationsStatus;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** État de l'exploitation et liste de préparation à l'ouverture (lecture seule, informative : n'active ni ne bloque rien). */
class OperationsController extends Controller
{
    public function __invoke(OperationsStatus $status): View
    {
        return view('admin.operations', ['d' => $status()]);
    }

    /** Installation volontaire du jeu de démonstration (huit cartes de l'accueil) : phrase à saisir, identité récente, journal d'audit. */
    public function installDemo(Request $request, InstallDemoData $install): RedirectResponse
    {
        $d = $request->validate(['phrase' => ['required', 'string', 'max:40']]);
        if (mb_strtoupper(trim($d['phrase'])) !== 'INSTALLER LA DEMO') {
            return back()->with('error', 'Saisissez exactement la phrase : INSTALLER LA DEMO');
        }
        try {
            $r = $install->byAdmin($request->user());
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        $msg = "Démonstration installée : {$r['demo_published']} service(s) de démonstration publié(s). L’accueil affiche {$r['home_visible']} carte(s).";

        return back()->with($r['home_visible'] >= 8 ? 'status' : 'error', $r['home_visible'] >= 8 ? $msg : $msg.' Il en faut huit : vérifiez qu’aucun vendeur de démonstration n’est suspendu et que la date du serveur est correcte.');
    }

    /** Retrait des données de démonstration : motif, confirmation, phrase à saisir, confirmation récente d'identité, journal d'audit. */
    public function purgeDemo(Request $request, PurgeDemoData $purge): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'confirm' => ['accepted'], 'phrase' => ['required', 'string', 'max:40']], ['confirm.accepted' => 'Cochez la case pour confirmer avoir fait une sauvegarde.']);
        if (mb_strtoupper(trim($d['phrase'])) !== 'RETIRER LA DEMO') {
            return back()->with('error', 'Saisissez exactement la phrase : RETIRER LA DEMO');
        }
        try {
            $r = $purge->byAdmin($request->user(), $d['reason']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Données de démonstration retirées : {$r['services_removed']} service(s), {$r['missions_removed']} mission(s), {$r['profiles_removed']} profil(s), {$r['users_removed']} compte(s). Conservés car référencés par l’historique : {$r['services_kept']} service(s), {$r['missions_kept']} mission(s), {$r['profiles_kept']} profil(s), {$r['users_kept']} compte(s).");
    }
}
