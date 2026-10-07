<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\PaymentGateways;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Admin\Actions\UpdateSettings;
use App\Modules\Admin\Queries\SettingsOverview;
use App\Modules\Admin\Settings\SettingDefinitions;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Paramètres de la plateforme saisis par l'administrateur (statut provisoire / approuvé, motif, historique). Les secrets sont chiffrés,
 * en écriture seule : jamais réaffichés, jamais journalisés. Chaque écriture exige la double authentification et une confirmation récente d'identité.
 */
class SettingsController extends Controller
{
    public function index(SettingsOverview $overview): View
    {
        return view('admin.settings', ['groups' => $overview->settings(), 'changes' => $overview->changes()]);
    }

    public function save(Request $request, UpdateSettings $update, string $group): RedirectResponse
    {
        abort_unless(isset(SettingDefinitions::groups()[$group]), 404);
        $d = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'v' => ['array'], 'clear' => ['array'], 'live_phrase' => ['nullable', 'string', 'max:40']]);
        $input = [];
        $clear = [];
        foreach (SettingDefinitions::groups()[$group]['keys'] as $key) {
            $name = str_replace('.', '_', $key);
            $input[$key] = $d['v'][$name] ?? null;
            if (! empty($d['clear'][$name])) {
                $clear[] = $key;
            }
        }
        try {
            $n = $update($request->user(), $group, $input, $request->boolean('approve'), $request->boolean('confirm'), $d['reason'], $clear, (string) ($d['live_phrase'] ?? ''));
        } catch (ModerationDenied|\DomainException $e) {
            return back()->withInput($request->except(['v', 'live_phrase']))->with('error', $e->getMessage());
        }

        return back()->with('status', $n.' paramètre'.($n > 1 ? 's' : '').' enregistré'.($n > 1 ? 's' : '').'. Les commandes existantes ne sont pas modifiées.');
    }

    /** Envoie un courriel de test à l'adresse de l'administrateur connecté (aucun autre destinataire). */
    public function testMail(Request $request, AdminAudit $audit): RedirectResponse
    {
        $user = $request->user();
        $audit->assertActor($user);
        if (in_array((string) config('mail.default'), ['log', 'array', ''], true)) {
            return back()->with('error', 'Le courrier est réglé sur « Journal » : aucun envoi réel. Choisissez SMTP, enregistrez, puis testez.');
        }
        try {
            Mail::raw('Ceci est un courriel de test envoyé depuis l’administration de FreeCI. Si vous le lisez, le courrier est correctement configuré.', fn ($m) => $m->to($user->email)->subject('FreeCI : courriel de test'));
        } catch (Throwable $e) {
            report($e);
            $audit->record($user, 'settings.test_mail', 'settings', 'courrier', null, null, 'refused', 'échec');

            return back()->with('error', 'Envoi impossible : '.mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 200).' Vérifiez le serveur, le port, l’identifiant et le mot de passe.');
        }
        $audit->record($user, 'settings.test_mail', 'settings', 'courrier', null, null, 'done');

        return back()->with('status', 'Courriel de test accepté par le serveur d’envoi, adressé à '.$user->email.'. Vérifiez votre boîte (et les indésirables).');
    }

    /** Un appel réel à Genius Pay (lecture du compte marchand), sans créer de paiement ; les clés ne sont jamais affichées. */
    public function testGenius(Request $request, AdminAudit $audit, PaymentGateways $gateways, string $env): RedirectResponse
    {
        abort_unless(in_array($env, GeniusPayConfig::ENVIRONMENTS, true), 404);
        $user = $request->user();
        $audit->assertActor($user);
        if (! GeniusPayConfig::ready($env)) {
            return back()->with('error', "Configuration « {$env} » incomplète ou non conforme (clés, secret du webhook, adresse en https) : aucun appel émis.");
        }
        $provider = $gateways->forEnvironment($env);
        $id = $provider->merchantId();
        $audit->record($user, 'settings.test_genius', 'settings', $env, null, null, $id === null ? 'refused' : 'done');
        if ($id === null) {
            return back()->with('error', 'Genius Pay n’a pas répondu comme attendu (clés refusées, réseau ou adresse).');
        }
        $status = $provider->merchantStatus();

        return $status === 'mismatch'
            ? back()->with('error', 'Le compte marchand répondant est DIFFÉRENT de celui déclaré : les nouveaux paiements réels seraient refusés.')
            : back()->with('status', "Genius Pay ({$env}) a répondu : clés acceptées".($status === 'ok' ? ', compte marchand conforme.' : '.'));
    }
}
