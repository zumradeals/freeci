<?php

namespace App\Http\Controllers;

use App\Modules\Orders\Actions\RespondToReview;
use App\Modules\Orders\Actions\SubmitReview;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\ReviewConflict;
use App\Modules\Orders\Queries\ReviewQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Avis d'une commande : le CLIENT dépose (une fois), le FREELANCE évalué répond (une fois). Aucun autre utilisateur, aucun administrateur ne dépose ni ne modifie. */
class ReviewController extends Controller
{
    public function show(Request $request, ReviewQueries $q, string $reference): View
    {
        $panel = $q->panelForReference($reference, $request->user());
        abort_if($panel === null, 404);

        return view('orders.review', ['panel' => $panel, 'key' => (string) Str::uuid(), 'space' => ($panel['isClient'] ?? true) ? 'client' : 'freelancer']);
    }

    public function store(Request $request, SubmitReview $submit, string $reference): RedirectResponse
    {
        $d = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['required', 'string', 'max:3000'], 'operation_key' => ['required', 'string', 'max:60']]);
        try {
            $submit($request->user(), $reference, (int) $d['rating'], $d['comment'], $d['operation_key']);
        } catch (OrderForbidden) {
            abort(404);
        } catch (ReviewConflict $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.review', $reference)->with('status', 'Votre avis est enregistré. Il ne pourra plus être modifié.');
    }

    public function reply(Request $request, RespondToReview $respond, ReviewQueries $q, string $review): RedirectResponse
    {
        $d = $request->validate(['body' => ['required', 'string', 'max:3000'], 'reference' => ['required', 'string', 'max:30']]);
        try {
            $respond($request->user(), $review, $d['body']);
        } catch (OrderForbidden) {
            abort(404);
        } catch (ReviewConflict $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.review', $d['reference'])->with('status', 'Votre réponse est publiée avec l’avis.');
    }
}
