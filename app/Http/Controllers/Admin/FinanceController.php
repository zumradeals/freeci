<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Finance\Actions\Beneficiaries;
use App\Modules\Finance\Actions\FinancialOperations;
use App\Modules\Finance\Queries\FinanceAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Finances (administrateurs, MFA, confirmation récente d'identité à chaque action d'écriture). Un seul administrateur peut préparer, confirmer et exécuter. Les actions revérifient tout côté serveur ;
 * aucun montant ni état n'est lu dans la requête (sauf le montant d'un remboursement demandé, plafonné par le registre).
 */
class FinanceController extends Controller
{
    public function index(Request $request, FinanceAdmin $q): View
    {
        return view('admin.finance.index', ['d' => $q->index($request->user()), 'key' => (string) Str::uuid()]);
    }

    public function show(Request $request, FinanceAdmin $q, string $reference): View
    {
        $d = $q->detail($request->user(), $reference);
        abort_if($d === null, 404);

        return view('admin.finance.show', ['d' => $d]);
    }

    public function requestRefund(Request $request, FinancialOperations $ops, string $decision): RedirectResponse
    {
        $d = $request->validate(['amount' => ['nullable', 'integer', 'min:1', 'max:100000000'], 'reason' => ['required', 'string', 'min:10', 'max:500'], 'operation_key' => ['required', 'string', 'max:60']]);

        return $this->run(fn () => $ops->requestRefund($request->user(), $decision, isset($d['amount']) ? (int) $d['amount'] : null, $d['reason'], $d['operation_key']), 'Remboursement préparé et fonds réservés. Rien n’est remboursé : relisez le récapitulatif, confirmez-le, puis exécutez.', fn ($id) => route('admin.finance.show', $ops->op($id)->reference));
    }

    public function requestPayout(Request $request, FinancialOperations $ops, string $reference): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500'], 'operation_key' => ['required', 'string', 'max:60']]);

        return $this->run(fn () => $ops->requestPayout($request->user(), $reference, $d['reason'], $d['operation_key']), 'Reversement préparé et fonds réservés. Rien n’est versé : relisez le récapitulatif, confirmez-le, puis enregistrez l’exécution manuelle.', fn ($id) => route('admin.finance.show', $ops->op($id)->reference));
    }

    public function act(Request $request, FinancialOperations $ops, string $reference, string $action): RedirectResponse
    {
        $id = $ops->idByReference($reference);
        abort_if($id === null, 404);
        $actor = $request->user();
        $note = fn () => $request->validate(['note' => ['required', 'string', 'min:10', 'max:500']])['note'];

        return match ($action) {
            'confirmer' => $this->run(function () use ($request, $ops, $actor, $id) {
                $d = $request->validate(['confirm' => ['accepted'], 'note' => ['nullable', 'string', 'max:500']], ['confirm.accepted' => 'Confirmez explicitement le récapitulatif (montant, bénéficiaire, environnement).']);
                $ops->approve($actor, $id, true, (string) ($d['note'] ?? ''));
            }, 'Récapitulatif confirmé : vous pouvez maintenant exécuter ou enregistrer l’opération. Rien n’est encore effectué.'),
            'refuser' => $this->run(fn () => $ops->reject($actor, $id, $note()), 'Opération refusée : la réservation est libérée.'),
            'annuler' => $this->run(fn () => $ops->cancel($actor, $id, $note()), 'Opération annulée : la réservation est libérée.'),
            'executer-api' => $this->run(fn () => $ops->executeRefundViaApi($actor, $id), 'Demande de remboursement envoyée à Genius Pay : le résultat est lu et vérifié, il peut être « à vérifier ».'),
            'rapprocher' => $this->run(function () use ($request, $ops, $actor, $id) {
                $d = $request->validate(['outcome' => ['required', 'in:refunded,not_refunded'], 'reference' => ['required', 'string', 'max:80'], 'proof' => ['required', 'string', 'min:20', 'max:500'], 'amount_confirm' => ['nullable', 'integer'], 'confirm' => ['accepted']]);
                $ops->reconcileManually($actor, $id, $d['outcome'], ['reference' => $d['reference'], 'proof' => $d['proof'], 'amount_confirm' => $d['amount_confirm'] ?? 0, 'confirm' => '1']);
            }, 'Rapprochement manuel enregistré (référence et justificatif conservés).'),
            'enregistrer' => $this->run(function () use ($request, $ops, $actor, $id) {
                $d = $request->validate(['external_reference' => ['required', 'string', 'max:80'], 'proof_note' => ['required', 'string', 'min:10', 'max:500'], 'amount_confirm' => ['required', 'integer'], 'confirm' => ['accepted']]);
                $ops->recordManual($actor, $id, $d);
            }, 'Exécution manuelle enregistrée (référence externe, justificatif et auteur conservés).'),
            default => abort(404),
        };
    }

    public function beneficiary(Request $request, Beneficiaries $b, string $id, string $action): RedirectResponse
    {
        $note = $request->validate(['note' => ['required', 'string', 'min:10', 'max:300']])['note'];

        return match ($action) {
            'verifier' => $this->run(fn () => $b->verify($request->user(), $id, $note), 'Destination vérifiée.'),
            'desactiver' => $this->run(fn () => $b->disable($request->user(), $id, $note), 'Destination désactivée.'),
            default => abort(404),
        };
    }

    private function run(callable $do, string $status, ?callable $to = null): RedirectResponse
    {
        try {
            $r = $do();
        } catch (ModerationDenied|\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return ($to !== null && is_string($r) ? redirect($to($r)) : back())->with('status', $status);
    }
}
