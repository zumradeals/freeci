<?php

namespace App\Http\Controllers;

use App\Modules\Files\Actions\ScanBriefFile;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Exceptions\FilesDisabled;
use App\Modules\Orders\Actions\DeliveryDraft;
use App\Modules\Orders\Actions\ExtensionRequests;
use App\Modules\Orders\Actions\RequestCorrection;
use App\Modules\Orders\Actions\SubmitDelivery;
use App\Modules\Orders\Actions\ValidateDelivery;
use App\Modules\Orders\Data\OrderDossier;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OperationKeyReused;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\RuleViolation;
use App\Modules\Orders\Queries\GetOrderDossier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Livraison, corrections, report d'échéance, validation. Chaque route revérifie côté serveur le rôle, l'état et la version :
 * les pages ne font que présenter, ce sont les actions (Orders\*) qui décident. Une page d'action absente de l'état courant
 * redirige vers le dossier.
 */
class DeliveryController extends Controller
{
    private function dossier(Request $request, string $reference, GetOrderDossier $dossier): OrderDossier
    {
        try {
            return $dossier($request->user(), $reference);
        } catch (OrderForbidden) {
            abort(404);
        }
    }

    private function gone(string $reference): RedirectResponse
    {
        return redirect()->route('orders.show', $reference)->with('error', 'Cette action n’est plus disponible : la commande a changé.');
    }

    /** Exécute une action ; les erreurs métier deviennent des réponses explicites, sans rien modifier. */
    private function run(string $reference, callable $do, string $success, ?string $back = null): RedirectResponse|Response
    {
        try {
            $result = $do();
        } catch (OrderForbidden|FileForbidden) {
            abort(404);
        } catch (RuleViolation $e) {
            return back()->withInput()->with('error', $e->getMessage())->with('reasons', $e->reasons);
        } catch (FileRejected|FilesDisabled $e) {
            return back()->with('error', $e->getMessage());
        } catch (InvalidTransition) {
            return response()->view('errors.order', ['title' => 'La commande a changé', 'message' => 'La commande a changé pendant que vous la consultiez : cette action n’est plus possible. Rien n’a été modifié.', 'back' => route('orders.show', $reference), 'backLabel' => 'Actualiser la commande'], 409);
        } catch (OperationKeyReused) {
            return response()->view('errors.order', ['title' => 'Envoi déjà traité', 'message' => 'Ce formulaire a déjà été utilisé avec un contenu différent. Rechargez la page.', 'back' => route('orders.show', $reference), 'backLabel' => 'Voir la commande'], 409);
        }
        $replayed = is_array($result) && ($result[1] ?? false) === true;

        return redirect($back ?? route('orders.show', $reference))->with('status', $replayed ? 'Cette action avait déjà été enregistrée.' : $success);
    }

    // ---------- Freelance : brouillon de livraison ----------

    public function edit(Request $request, string $reference, GetOrderDossier $dossier): View|RedirectResponse
    {
        $d = $this->dossier($request, $reference, $dossier);
        if ($d->perspective !== 'freelancer' || $d->delivery['draft'] === null) {
            return $this->gone($reference);
        }

        return view('orders.delivery', ['d' => $d, 'draft' => $d->delivery['draft'], 'space' => 'freelancer', 'limitMb' => (int) config('freeci.files.delivery_max_mb')]);
    }

    public function saveMessage(Request $request, string $reference, DeliveryDraft $draft): RedirectResponse|Response
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:'.DeliveryDraft::MESSAGE_MAX]]);

        return $this->run($reference, fn () => $draft->saveMessage($request->user(), $reference, (string) ($data['message'] ?? '')), 'Brouillon enregistré. Le client ne le voit pas avant la soumission.', route('orders.delivery', $reference));
    }

    public function upload(Request $request, string $reference, DeliveryDraft $draft, ScanBriefFile $scan): RedirectResponse|Response
    {
        $request->validate(['file' => ['required', 'file']], ['file.required' => 'Choisissez un fichier.', 'file.file' => 'Le téléversement a échoué : réessayez.', 'file.uploaded' => 'Le fichier dépasse la taille autorisée.']);

        return $this->run($reference, function () use ($request, $reference, $draft, $scan) {
            $id = $draft->addFile($request->user(), $reference, $request->file('file'));
            app()->terminating(fn () => $scan($id));            // contrôle après la réponse ; repris par freeci:files:scan si indisponible
        }, 'Fichier reçu. Il ne pourra être livré qu’après le contrôle de sécurité.', route('orders.delivery', $reference));
    }

    public function removeFile(Request $request, string $reference, string $file, DeliveryDraft $draft): RedirectResponse|Response
    {
        return $this->run($reference, fn () => $draft->removeFile($request->user(), $reference, $file), 'Fichier retiré du brouillon.', route('orders.delivery', $reference));
    }

    public function submitConfirm(Request $request, string $reference, GetOrderDossier $dossier): View|RedirectResponse
    {
        $d = $this->dossier($request, $reference, $dossier);
        $draft = $d->delivery['draft'] ?? null;
        if ($d->perspective !== 'freelancer' || $draft === null || $draft['id'] === null) {
            return $this->gone($reference);
        }
        if (! $draft['canSubmit']) {
            return redirect()->route('orders.delivery', $reference)->with('error', 'La livraison ne peut pas encore être soumise.')->with('reasons', $draft['blockers']);
        }

        return view('orders.step', ['d' => $d, 'kind' => 'submit', 'draft' => $draft, 'operationKey' => (string) Str::uuid(), 'space' => 'freelancer']);
    }

    public function submit(Request $request, string $reference, SubmitDelivery $submit): RedirectResponse|Response
    {
        $data = $request->validate(['delivery_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'], 'operation_key' => ['required', 'string', 'max:80']]);

        return $this->run($reference, fn () => $submit($request->user(), $reference, $data['delivery_id'], (int) $data['expected_version'], $data['operation_key']),
            'Livraison soumise au client. Elle est conservée telle quelle ; une nouvelle version ne la remplace pas.');
    }

    // ---------- Client : examen ----------

    public function correctionForm(Request $request, string $reference, GetOrderDossier $dossier): View|RedirectResponse
    {
        $d = $this->dossier($request, $reference, $dossier);
        if (! ($d->delivery['canDecide'] ?? false)) {
            return $this->gone($reference);
        }

        return view('orders.step', ['d' => $d, 'kind' => 'correction', 'operationKey' => (string) Str::uuid(), 'space' => 'client']);
    }

    public function correction(Request $request, string $reference, RequestCorrection $correct): RedirectResponse|Response
    {
        $data = $request->validate(['delivery_id' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:'.RequestCorrection::REASON_MAX], 'expected_version' => ['required', 'integer', 'min:1'], 'operation_key' => ['required', 'string', 'max:80']]);

        return $this->run($reference, fn () => $correct($request->user(), $reference, $data['delivery_id'], $data['reason'], (int) $data['expected_version'], $data['operation_key']),
            'Demande de correction envoyée. Le freelance peut y répondre par une nouvelle version.');
    }

    public function validateForm(Request $request, string $reference, GetOrderDossier $dossier): View|RedirectResponse
    {
        $d = $this->dossier($request, $reference, $dossier);
        if (! ($d->delivery['canDecide'] ?? false)) {
            return $this->gone($reference);
        }

        return view('orders.step', ['d' => $d, 'kind' => 'validate', 'operationKey' => (string) Str::uuid(), 'space' => 'client']);
    }

    public function validateDelivery(Request $request, string $reference, ValidateDelivery $validate): RedirectResponse|Response
    {
        $data = $request->validate(['delivery_id' => ['required', 'uuid'], 'expected_version' => ['required', 'integer', 'min:1'], 'operation_key' => ['required', 'string', 'max:80'], 'confirm' => ['accepted']],
            ['confirm.accepted' => 'Cochez la case pour confirmer que vous avez examiné la livraison.']);

        return $this->run($reference, fn () => $validate($request->user(), $reference, $data['delivery_id'], (int) $data['expected_version'], $data['operation_key']),
            'Livraison validée : la commande est clôturée. Le reversement n’est ni confirmé ni déclenché par cette étape.');
    }

    // ---------- Report d'échéance ----------

    public function extensionForm(Request $request, string $reference, GetOrderDossier $dossier): View|RedirectResponse
    {
        $d = $this->dossier($request, $reference, $dossier);
        if (! ($d->delivery['canRequestExtension'] ?? false)) {
            return $this->gone($reference);
        }

        return view('orders.step', ['d' => $d, 'kind' => 'extension', 'operationKey' => (string) Str::uuid(), 'space' => 'freelancer']);
    }

    public function extension(Request $request, string $reference, ExtensionRequests $ext): RedirectResponse|Response
    {
        $data = $request->validate(['proposed_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:1000'], 'expected_version' => ['required', 'integer', 'min:1'], 'operation_key' => ['required', 'string', 'max:80']]);

        return $this->run($reference, fn () => $ext->request($request->user(), $reference, $data['proposed_date'], $data['reason'], (int) $data['expected_version'], $data['operation_key']),
            'Proposition envoyée. L’échéance ne change que si le client l’accepte.');
    }

    public function extensionAnswerForm(Request $request, string $reference, string $decision, GetOrderDossier $dossier): View|RedirectResponse
    {
        $d = $this->dossier($request, $reference, $dossier);
        if (! ($d->delivery['canAnswerExtension'] ?? false) || ! in_array($decision, ['accepter', 'refuser'], true)) {
            return $this->gone($reference);
        }

        return view('orders.step', ['d' => $d, 'kind' => $decision === 'accepter' ? 'extension-accept' : 'extension-decline', 'operationKey' => (string) Str::uuid(), 'space' => 'client']);
    }

    public function extensionAnswer(Request $request, string $reference, string $decision, ExtensionRequests $ext): RedirectResponse|Response
    {
        abort_unless(in_array($decision, ['accepter', 'refuser'], true), 404);
        $data = $request->validate(['extension_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:1000'], 'expected_version' => ['required', 'integer', 'min:1'], 'operation_key' => ['required', 'string', 'max:80']]);

        return $this->run($reference, fn () => $ext->answer($request->user(), $reference, (int) $data['extension_id'], $decision === 'accepter', $data['note'] ?? null, (int) $data['expected_version'], $data['operation_key']),
            $decision === 'accepter' ? 'Report accepté : la nouvelle échéance est en vigueur.' : 'Report refusé : l’échéance reste inchangée.');
    }

    public function extensionWithdraw(Request $request, string $reference, GetOrderDossier $dossier, ExtensionRequests $ext): RedirectResponse|Response
    {
        $data = $request->validate(['extension_id' => ['required', 'integer'], 'expected_version' => ['required', 'integer', 'min:1'], 'operation_key' => ['required', 'string', 'max:80']]);

        return $this->run($reference, fn () => $ext->withdraw($request->user(), $reference, (int) $data['extension_id'], (int) $data['expected_version'], $data['operation_key']), 'Proposition de report retirée.');
    }
}
