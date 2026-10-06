<?php

namespace App\Http\Controllers;

use App\Modules\Files\Actions\DownloadBriefFile;
use App\Modules\Files\Actions\RemoveBriefFile;
use App\Modules\Files\Actions\ScanBriefFile;
use App\Modules\Files\Actions\UploadBriefFile;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Exceptions\FilesDisabled;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderForbidden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Pièces jointes privées du brief : dépôt (client), retrait (client), téléchargement signé (les deux parties, fichier contrôlé). */
class OrderFileController extends Controller
{
    public function store(Request $request, string $reference, UploadBriefFile $upload, ScanBriefFile $scan): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file']], ['file.required' => 'Choisissez un fichier.', 'file.file' => 'Le téléversement a échoué : réessayez.', 'file.uploaded' => 'Le fichier dépasse la taille autorisée.']);

        try {
            $id = $upload($request->user(), $reference, $request->file('file'));
        } catch (OrderForbidden) {
            abort(404);
        } catch (FilesDisabled $e) {
            return redirect(route('orders.show', $reference).'#brief')->with('error', $e->getMessage());
        } catch (FileRejected $e) {
            return redirect(route('orders.show', $reference).'#brief')->with('error', $e->getMessage());
        } catch (InvalidTransition) {
            return redirect(route('orders.show', $reference).'#brief')->with('error', 'Le brief n’accepte plus de fichier : la commande a changé.');
        }

        // Le contrôle s'exécute APRÈS la réponse (aucun worker requis) ; un échec laisse le fichier bloqué en quarantaine.
        app()->terminating(fn () => $scan($id));

        return redirect(route('orders.show', $reference).'#brief')->with('status', 'Fichier reçu. Il sera téléchargeable après le contrôle de sécurité.');
    }

    public function destroy(Request $request, string $reference, string $file, RemoveBriefFile $remove): RedirectResponse
    {
        try {
            $remove($request->user(), $reference, $file);
        } catch (OrderForbidden|FileForbidden) {
            abort(404);
        } catch (InvalidTransition) {
            return redirect(route('orders.show', $reference).'#brief')->with('error', 'Le travail a démarré : les fichiers du brief ne peuvent plus être retirés.');
        }

        return redirect(route('orders.show', $reference).'#brief')->with('status', 'Fichier retiré.');
    }

    public function download(Request $request, string $reference, string $file, DownloadBriefFile $download): BinaryFileResponse
    {
        // Lien signé (5 min) lié à l'utilisateur connecté, PUIS nouvelle vérification des droits et de l'état du fichier.
        abort_unless($request->query('u') === $request->user()->getKey(), 404);
        try {
            $f = $download->open($request->user(), $reference, $file);
        } catch (FileForbidden) {
            abort(404);
        }

        return response()->download($f['path'], $f['name'], [
            'Content-Type' => 'application/octet-stream',            // jamais interprété par le navigateur
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
