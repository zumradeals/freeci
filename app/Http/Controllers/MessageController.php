<?php

namespace App\Http\Controllers;

use App\Modules\Files\Actions\ScanBriefFile;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Messaging\Actions\Conversations;
use App\Modules\Messaging\Actions\MessageFiles;
use App\Modules\Messaging\Actions\SendMessage;
use App\Modules\Messaging\Exceptions\MessagingConflict;
use App\Modules\Messaging\Exceptions\MessagingForbidden;
use App\Modules\Messaging\Queries\Inbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Messagerie privée : participants seulement (jamais d'accès implicite, administrateur compris). Un message ne modifie aucune commande. */
class MessageController extends Controller
{
    public function __construct(private Conversations $conversations, private Inbox $inbox, private SendMessage $send) {}

    private function space(Request $request): string
    {
        return $request->query('espace') === 'freelance' && $request->user()->hasRole('freelance') ? 'freelancer' : 'client';
    }

    public function index(Request $request): View
    {
        return view('messages.index', ['conversations' => $this->inbox->list($request->user()), 'blocked' => $this->inbox->blockedContacts($request->user()), 'space' => $this->space($request)]);
    }

    public function show(Request $request, string $conversation): View
    {
        try {
            $before = $request->query('avant') !== null ? (int) $request->query('avant') : null;
            $t = $this->inbox->thread($request->user(), $conversation, $before);
            if ($before === null) {
                $this->conversations->markRead($request->user(), $conversation);        // lu = la dernière page a été affichée
            }
        } catch (MessagingForbidden) {
            abort(404);
        }

        return view('messages.show', ['t' => $t, 'operationKey' => (string) Str::uuid(), 'space' => $this->space($request), 'conversations' => $this->inbox->list($request->user()), 'blocked' => $this->inbox->blockedContacts($request->user())]);
    }

    public function store(Request $request, string $conversation, ScanBriefFile $scan): RedirectResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:20000'], 'file' => ['nullable', 'file'], 'client_key' => ['required', 'string', 'max:80']], ['file.file' => 'Le téléversement a échoué : réessayez.']);
        try {
            [, $replayed, $fileId] = ($this->send)($request->user(), $conversation, (string) ($data['body'] ?? ''), $request->file('file'), $data['client_key']);
        } catch (MessagingForbidden) {
            abort(404);
        } catch (MessagingConflict $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        if ($fileId !== null) {
            app()->terminating(fn () => $scan($fileId));         // contrôle après la réponse ; repris par freeci:files:scan
        }

        return redirect()->route('messages.show', array_filter(['conversation' => $conversation, 'espace' => $request->query('espace')]))->with('status', $replayed ? 'Ce message avait déjà été envoyé.' : ($fileId ? 'Message envoyé. La pièce jointe sera téléchargeable après le contrôle de sécurité.' : 'Message envoyé.'));
    }

    // ---------- ouverture d'une conversation selon le contexte ----------

    public function startService(Request $request, string $slug): View|RedirectResponse
    {
        try {
            [$c, $created] = $this->conversations->forService($request->user(), $slug);
        } catch (MessagingForbidden) {
            abort(404);
        } catch (MessagingConflict $e) {
            return redirect()->route('services.show', $slug)->with('error', $e->getMessage());
        }
        if (! $created && $c->last_message_id) {
            return redirect()->route('messages.show', $c->getKey());
        }

        return view('messages.start', ['context' => $c->context_title, 'kind' => 'service', 'action' => route('messages.start.service.store', $slug), 'operationKey' => (string) Str::uuid(), 'space' => 'client']);
    }

    public function storeService(Request $request, string $slug, ScanBriefFile $scan): RedirectResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:20000'], 'file' => ['nullable', 'file'], 'client_key' => ['required', 'string', 'max:80']]);

        return $this->startAndSend($request, fn () => $this->conversations->forService($request->user(), $slug), $data, $scan, route('services.show', $slug));
    }

    public function startProposal(Request $request, string $proposal): View|RedirectResponse
    {
        try {
            [$c] = $this->conversations->forProposal($request->user(), $proposal);
        } catch (MessagingForbidden) {
            abort(404);
        } catch (MessagingConflict $e) {
            return redirect()->route('client.missions')->with('error', $e->getMessage());
        }
        if ($c->last_message_id) {
            return redirect()->route('messages.show', $c->getKey());
        }

        return view('messages.start', ['context' => $c->context_title, 'kind' => 'proposal', 'action' => route('messages.start.proposal.store', $proposal), 'operationKey' => (string) Str::uuid(), 'space' => $c->client_id === $request->user()->getKey() ? 'client' : 'freelancer']);
    }

    public function storeProposal(Request $request, string $proposal, ScanBriefFile $scan): RedirectResponse
    {
        $data = $request->validate(['body' => ['nullable', 'string', 'max:20000'], 'file' => ['nullable', 'file'], 'client_key' => ['required', 'string', 'max:80']]);

        return $this->startAndSend($request, fn () => $this->conversations->forProposal($request->user(), $proposal), $data, $scan, route('client.missions'));
    }

    public function order(Request $request, string $reference): RedirectResponse
    {
        try {
            $c = $this->conversations->forOrder($request->user(), $reference);
        } catch (MessagingForbidden) {
            abort(404);
        }

        return redirect()->route('messages.show', ['conversation' => $c->getKey(), 'espace' => $c->freelancer_id === $request->user()->getKey() ? 'freelance' : null]);
    }

    private function startAndSend(Request $request, callable $open, array $data, ScanBriefFile $scan, string $fallback): RedirectResponse
    {
        try {
            [$c] = $open();
            [, , $fileId] = ($this->send)($request->user(), $c->getKey(), (string) ($data['body'] ?? ''), $request->file('file'), $data['client_key']);
        } catch (MessagingForbidden) {
            abort(404);
        } catch (MessagingConflict $e) {
            return redirect($fallback)->with('error', $e->getMessage());
        }
        if ($fileId !== null) {
            app()->terminating(fn () => $scan($fileId));
        }

        return redirect()->route('messages.show', ['conversation' => $c->getKey(), 'espace' => $c->freelancer_id === $request->user()->getKey() ? 'freelance' : null])->with('status', 'Message envoyé.');
    }

    // ---------- blocage ----------

    public function block(Request $request, string $conversation): RedirectResponse
    {
        try {
            $this->conversations->block($request->user(), $conversation);
        } catch (MessagingForbidden) {
            abort(404);
        }

        return back()->with('status', 'Contact bloqué : les nouveaux échanges non nécessaires sont suspendus. L’historique est conservé et les échanges indispensables à une commande active restent possibles.');
    }

    public function unblock(Request $request, string $conversation): RedirectResponse
    {
        try {
            $this->conversations->unblock($request->user(), $conversation);
        } catch (MessagingForbidden) {
            abort(404);
        }

        return back()->with('status', 'Contact débloqué.');
    }

    // ---------- pièces jointes ----------

    public function download(Request $request, string $file, MessageFiles $files): BinaryFileResponse
    {
        abort_unless($request->query('u') === $request->user()->getKey(), 404);
        try {
            $f = $files->open($request->user(), $file);
        } catch (FileForbidden) {
            abort(404);
        }

        return response()->download($f['path'], $f['name'], ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }
}
