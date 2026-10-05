<?php

namespace App\Http\Controllers;

use App\Modules\Notifications\Actions\NotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Centre de notifications et préférences. Toujours bornés à l'utilisateur connecté ; le lien d'une notification passe par les écrans cibles, qui revérifient les droits. */
class NotificationController extends Controller
{
    public function __construct(private NotificationCenter $center) {}

    private function space(Request $request): string
    {
        return $request->query('espace') === 'freelance' && $request->user()->hasRole('freelance') ? 'freelancer' : 'client';
    }

    public function index(Request $request): View
    {
        return view('notifications.index', ['page' => $this->center->page($request->user()), 'unread' => $this->center->unread($request->user()), 'space' => $this->space($request)]);
    }

    public function open(Request $request, int $id): RedirectResponse
    {
        $url = $this->center->open($request->user(), $id) ?? abort(404);

        return redirect($url);
    }

    public function read(Request $request, int $id): RedirectResponse
    {
        $this->center->markRead($request->user(), $id);       // uniquement les siennes : celles d'autrui sont ignorées

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $n = $this->center->markAllRead($request->user());

        return redirect()->route('notifications.index')->with('status', $n > 0 ? "{$n} notification".($n > 1 ? 's' : '').' marquée'.($n > 1 ? 's' : '').' comme lue'.($n > 1 ? 's' : '').'.' : 'Aucune notification non lue.');
    }

    public function preferences(Request $request): View
    {
        return view('notifications.preferences', $this->center->preferences($request->user()) + ['space' => $this->space($request)]);
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $request->validate(['pref' => ['nullable', 'array']]);
        $this->center->savePreferences($request->user(), (array) $request->input('pref', []));

        return redirect()->route('notifications.preferences')->with('status', 'Préférences enregistrées. Les notifications indispensables à votre compte et à vos commandes restent actives.');
    }
}
