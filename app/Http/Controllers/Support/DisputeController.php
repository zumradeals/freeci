<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Support\Actions\OpenDispute;
use App\Modules\Support\Exceptions\SupportConflict;
use App\Modules\Support\Queries\DisputeOptions;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Ouvrir un litige, demander l'annulation après paiement, ou déposer une réclamation après versement — depuis le dossier de la commande. */
class DisputeController extends Controller
{
    public function create(Request $request, DisputeOptions $options, string $reference): View
    {
        $o = $this->order($request, $reference);

        return view('support.dispute', ['reference' => $reference, 'opt' => $options->for($o->id, $o->state), 'kinds' => CaseRules::KINDS, 'key' => (string) Str::uuid(), 'state' => $o->state]);
    }

    public function store(Request $request, OpenDispute $open, string $reference): RedirectResponse
    {
        $this->order($request, $reference);
        $d = $request->validate(['kind' => ['required', 'string', 'max:12'], 'reason' => ['required', 'string', 'max:10000'], 'operation_key' => ['required', 'string', 'max:80'], 'confirm' => ['accepted']]);
        try {
            [$ref] = $open($request->user(), $reference, $d['kind'], $d['reason'], $d['operation_key']);
        } catch (SupportConflict|OrderForbidden $e) {
            return back()->withInput()->with('error', $e instanceof OrderForbidden ? 'Commande introuvable.' : $e->getMessage());
        }

        return redirect()->route('support.show', $ref)->with('status', $d['kind'] === 'claim'
            ? 'Réclamation enregistrée. Le reversement ayant déjà été envoyé, FreeCI ne peut ni le bloquer ni le récupérer : le traitement financier dépend du prestataire de paiement et n’est pas garanti.'
            : 'Dossier ouvert. Les actions de la commande sont suspendues pendant l’examen ; le reversement non exécuté est bloqué en interne. Ajoutez vos explications et vos preuves ci-dessous.');
    }

    private function order(Request $request, string $reference): object
    {
        $uid = $request->user()->getKey();
        $o = DB::table('orders')->where('reference', $reference)->where(fn ($q) => $q->where('client_id', $uid)->orWhere('freelancer_id', $uid))->first(['id', 'state']);
        abort_if($o === null, 404);

        return $o;
    }
}
