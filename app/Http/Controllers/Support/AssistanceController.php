<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Exceptions\FilesDisabled;
use App\Modules\Support\Actions\CaseThread;
use App\Modules\Support\Actions\OpenCase;
use App\Modules\Support\Exceptions\SupportConflict;
use App\Modules\Support\Queries\CaseFiles;
use App\Modules\Support\Queries\RequesterCases;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Espace « Assistance » de l'utilisateur : contacter le support, signaler, suivre ses dossiers, répondre, joindre des pièces contrôlées. */
class AssistanceController extends Controller
{
    public function __construct(private RequesterCases $cases) {}

    public function index(Request $request): View
    {
        return view('support.index', ['cases' => $this->cases->list($request->user())]);
    }

    public function create(Request $request): View
    {
        return view('support.new', ['categories' => CaseRules::SUPPORT_CATEGORIES, 'order' => (string) $request->query('commande', ''), 'key' => (string) Str::uuid()]);
    }

    public function store(Request $request, OpenCase $open): RedirectResponse
    {
        $d = $request->validate(['subject' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:10000'], 'category' => ['required', 'string', 'max:20'],
            'order' => ['nullable', 'string', 'max:30'], 'operation_key' => ['required', 'string', 'max:80']]);
        try {
            [$ref] = $open->support($request->user(), $d['subject'], $d['body'], $d['category'], $d['order'] ?? null, $d['operation_key']);
        } catch (SupportConflict $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('support.show', $ref)->with('status', 'Votre demande est enregistrée. L’équipe la prendra en charge : vous pouvez suivre son avancement ici.');
    }

    public function reportForm(Request $request, string $type, string $id): View
    {
        abort_unless(isset(CaseRules::TARGETS[$type]), 404);

        return view('support.report', ['type' => $type, 'id' => $id, 'typeLabel' => CaseRules::TARGETS[$type], 'reasons' => CaseRules::REPORT_REASONS, 'key' => (string) Str::uuid()]);
    }

    public function report(Request $request, OpenCase $open, string $type, string $id): RedirectResponse
    {
        abort_unless(isset(CaseRules::TARGETS[$type]), 404);
        $d = $request->validate(['reason' => ['required', 'string', 'max:20'], 'body' => ['required', 'string', 'max:10000'], 'operation_key' => ['required', 'string', 'max:80']]);
        try {
            [$ref] = $open->report($request->user(), $type, $id, $d['reason'], $d['body'], $d['operation_key']);
        } catch (SupportConflict $e) {
            return redirect()->route('support.index')->with('error', $e->getMessage());
        }

        return redirect()->route('support.show', $ref)->with('status', 'Signalement enregistré. La personne signalée n’en est pas informée ; vous pouvez suivre le dossier ici.');
    }

    public function show(Request $request, string $reference): View
    {
        $c = $this->cases->show($request->user(), $reference);
        abort_if($c === null, 404);

        return view('support.show', ['c' => $c, 'key' => (string) Str::uuid()]);
    }

    public function reply(Request $request, CaseThread $thread, string $reference): RedirectResponse
    {
        $d = $request->validate(['body' => ['required', 'string', 'max:10000'], 'client_key' => ['required', 'string', 'max:40'], 'file' => ['nullable', 'file']]);
        $c = $this->cases->show($request->user(), $reference);
        abort_if($c === null, 404);
        try {
            $thread->post($request->user(), $reference, $d['body'], $c['disputeLike'] ? 'parties' : 'requester', $request->file('file'), $d['client_key']);
        } catch (SupportConflict|FileRejected|FilesDisabled $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('support.show', $reference)->with('status', 'Message envoyé.');
    }

    public function download(Request $request, CaseFiles $files, string $file): BinaryFileResponse
    {
        abort_unless((string) $request->query('u') === $request->user()->getKey(), 404);
        try {
            $f = $files->forParty($request->user(), $file);
        } catch (FileForbidden) {
            abort(404);
        }

        return response()->download($f['path'], $f['name'], ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
