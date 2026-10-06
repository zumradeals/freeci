<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Exceptions\FilesDisabled;
use App\Modules\Support\Actions\CaseThread;
use App\Modules\Support\Actions\StaffCases;
use App\Modules\Support\Exceptions\SupportConflict;
use App\Modules\Support\Exceptions\SupportForbidden;
use App\Modules\Support\Queries\CaseDossier;
use App\Modules\Support\Queries\CaseFiles;
use App\Modules\Support\Queries\StaffQueue;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Assistance côté personnel habilité : file, affectation, ouverture motivée, échanges, décision. Chaque action est revérifiée et journalisée par les actions métier. */
class SupportCaseController extends Controller
{
    public function __construct(private StaffQueue $queue, private CaseDossier $dossier, private StaffCases $cases) {}

    public function index(Request $request): View
    {
        $f = ['kind' => (string) $request->query('type', ''), 'status' => (string) $request->query('statut', ''), 'who' => (string) $request->query('qui', ''), 'q' => (string) $request->query('q', '')];
        $tab = in_array($request->query('onglet'), ['suivis', 'financier'], true) ? $request->query('onglet') : 'dossiers';

        return view('admin.support.index', [
            'counts' => $this->queue->counts($request->user()), 'tab' => $tab, 'f' => $f, 'kinds' => CaseRules::KINDS, 'statuses' => CaseRules::STAFF_STATUS,
            'page' => $tab === 'dossiers' ? $this->queue->list($request->user(), $f, (int) config('freeci.support.page_size')) : null,
            'followUps' => $tab === 'suivis' ? $this->queue->followUps() : [], 'toProcess' => $tab === 'financier' ? $this->queue->toProcess() : [],
        ]);
    }

    public function show(Request $request, AdminAudit $audit, string $reference): View
    {
        $m = $this->dossier->meta($request->user(), $reference);
        abort_if($m === null, 404);
        $content = $m['accessOpen'] ? $this->dossier->content($request->user(), $reference) : null;
        if ($content !== null) {
            $audit->record($request->user(), 'case.view', 'case', $reference, $m['subject'], null, 'done', 'consultation du contenu du dossier');
        }
        $staff = $request->user()->isAdministrator() ? $this->assignable() : [];

        return view('admin.support.show', ['m' => $m, 'content' => $content, 'outcomes' => CaseRules::OUTCOMES, 'financial' => CaseRules::FINANCIAL, 'staff' => $staff, 'statuses' => CaseRules::STAFF_STATUS, 'key' => (string) Str::uuid(), 'isAdmin' => $request->user()->isAdministrator()]);
    }

    /** @return array<string, string> */
    private function assignable(): array
    {
        return DB::table('users')->whereIn('id', DB::table('staff_grants')->whereNull('revoked_at')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->select('user_id'))
            ->whereNull('suspended_at')->whereNotNull('email_verified_at')->whereNotNull('two_factor_confirmed_at')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function claim(Request $request, string $reference): RedirectResponse
    {
        return $this->act(fn () => $this->cases->claim($request->user(), $reference), $reference, 'Dossier pris en charge : il vous est affecté.');
    }

    public function assign(Request $request, string $reference): RedirectResponse
    {
        $d = $request->validate(['assignee' => ['required', 'string', 'max:40']]);

        return $this->act(fn () => $this->cases->assignTo($request->user(), $reference, $d['assignee']), $reference, 'Dossier affecté.');
    }

    public function release(Request $request, string $reference): RedirectResponse
    {
        return $this->act(fn () => $this->cases->release($request->user(), $reference), $reference, 'Affectation terminée : votre accès au contenu du dossier est retiré.');
    }

    public function open(Request $request, string $reference): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        return $this->act(fn () => $this->cases->openAccess($request->user(), $reference, $d['reason']), $reference, 'Dossier ouvert. Vos consultations sont journalisées.');
    }

    public function status(Request $request, string $reference): RedirectResponse
    {
        $d = $request->validate(['status' => ['required', 'string', 'max:20'], 'version' => ['required', 'integer'], 'priority' => ['nullable', 'string', 'max:8']]);

        return $this->act(function () use ($request, $reference, $d) {
            $this->cases->setStatus($request->user(), $reference, $d['status'], (int) $d['version']);
            if (! empty($d['priority'])) {
                $this->cases->setPriority($request->user(), $reference, $d['priority']);
            }
        }, $reference, 'Dossier mis à jour.');
    }

    public function reply(Request $request, CaseThread $thread, string $reference): RedirectResponse
    {
        $d = $request->validate(['body' => ['required', 'string', 'max:10000'], 'visibility' => ['required', 'string', 'max:10'], 'client_key' => ['required', 'string', 'max:40'], 'file' => ['nullable', 'file']]);

        return $this->act(fn () => $thread->post($request->user(), $reference, $d['body'], $d['visibility'], $request->file('file'), $d['client_key']), $reference, $d['visibility'] === 'internal' ? 'Note interne ajoutée (invisible des parties).' : 'Message envoyé.');
    }

    public function close(Request $request, string $reference): RedirectResponse
    {
        $d = $request->validate(['note' => ['required', 'string', 'min:10', 'max:1000']]);

        return $this->act(fn () => $this->cases->close($request->user(), $reference, $d['note']), $reference, 'Dossier clos.');
    }

    public function decide(Request $request, string $reference): RedirectResponse
    {
        $d = $request->validate(['outcome' => ['required', 'string', 'max:20'], 'reason' => ['required', 'string', 'max:3000'], 'financial_need' => ['required', 'string', 'max:10'], 'financial_note' => ['nullable', 'string', 'max:600'],
            'version' => ['required', 'integer'], 'operation_key' => ['required', 'string', 'max:80'], 'confirm' => ['accepted']]);

        return $this->act(fn () => $this->cases->decide($request->user(), $reference, ['outcome' => $d['outcome'], 'reason' => $d['reason'], 'financial_need' => $d['financial_need'], 'financial_note' => $d['financial_note'] ?? null], (int) $d['version'], $d['operation_key']),
            $reference, 'Décision enregistrée et notifiée aux parties. Si une suite financière est à traiter, elle apparaît dans « À traiter financièrement » : rien n’a été remboursé ni versé.');
    }

    public function fromFollowUp(Request $request, int $id): RedirectResponse
    {
        try {
            $ref = $this->cases->fromFollowUp($request->user(), $id);
        } catch (ModerationDenied|SupportConflict|SupportForbidden $e) {
            return redirect()->route('admin.support', ['onglet' => 'suivis'])->with('error', $e->getMessage());
        }

        return redirect()->route('admin.support.show', $ref)->with('status', 'Dossier de suivi ouvert. Ce n’est pas un litige : la commande n’est pas modifiée.');
    }

    public function download(Request $request, CaseFiles $files, string $reference, string $file): BinaryFileResponse
    {
        abort_unless((string) $request->query('u') === $request->user()->getKey(), 404);
        try {
            $f = $files->forStaff($request->user(), $reference, $file);
        } catch (FileForbidden) {
            abort(404);
        }

        return response()->download($f['path'], $f['name'], ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    private function act(callable $do, string $reference, string $success): RedirectResponse
    {
        try {
            $do();
        } catch (ModerationDenied|SupportConflict|FileRejected|FilesDisabled $e) {
            return redirect()->route('admin.support.show', $reference)->withInput()->with('error', $e->getMessage());
        } catch (SupportForbidden) {
            abort(404);
        }

        return redirect()->route('admin.support.show', $reference)->with('status', $success);
    }
}
