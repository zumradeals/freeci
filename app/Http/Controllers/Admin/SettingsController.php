<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\UpdateSettings;
use App\Modules\Admin\Queries\SettingsOverview;
use App\Modules\Admin\Settings\SettingDefinitions;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Paramètres de la plateforme saisis par l'administrateur (statut provisoire / approuvé, motif, historique). Aucun secret ici. */
class SettingsController extends Controller
{
    public function index(SettingsOverview $overview): View
    {
        return view('admin.settings', ['groups' => $overview->settings(), 'changes' => $overview->changes()]);
    }

    public function save(Request $request, UpdateSettings $update, string $group): RedirectResponse
    {
        abort_unless(isset(SettingDefinitions::groups()[$group]), 404);
        $d = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'v' => ['array']]);
        $input = [];
        foreach (SettingDefinitions::groups()[$group]['keys'] as $key) {
            $input[$key] = $d['v'][str_replace('.', '_', $key)] ?? null;
        }
        try {
            $n = $update($request->user(), $group, $input, $request->boolean('approve'), $request->boolean('confirm'), $d['reason']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', $n.' paramètre'.($n > 1 ? 's' : '').' enregistré'.($n > 1 ? 's' : '').'. Les commandes existantes ne sont pas modifiées.');
    }
}
