<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\ModerateReviews;
use App\Modules\Admin\Queries\ReviewModerationList;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Orders\Support\ReviewVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Modération des avis (administrateurs) : masquer / rétablir avec catégorie et motif. Lecture seule du contenu : jamais de réécriture ni de dépôt à la place d'un client. */
class ReviewModerationController extends Controller
{
    public function index(Request $request, ReviewModerationList $list): View
    {
        $filter = in_array($request->query('filtre'), ['reported', 'hidden', 'test', 'all'], true) ? $request->query('filtre') : 'reported';

        return view('admin.reviews', ['page' => $list->page($filter === 'all' ? 'public' : $filter, (int) config('freeci.admin.page_size')), 'filter' => $filter, 'counts' => $list->counts(), 'categories' => ReviewVisibility::MODERATION_CATEGORIES]);
    }

    public function act(Request $request, ModerateReviews $moderate, string $review, string $action): RedirectResponse
    {
        $d = $request->validate(['target' => ['required', 'in:review,reply'], 'category' => ['nullable', 'string', 'max:20'], 'reason' => ['required', 'string', 'min:20', 'max:1000']]);
        try {
            $action === 'masquer' ? $moderate->hide($request->user(), $review, $d['target'], (string) ($d['category'] ?? ''), $d['reason']) : $moderate->restore($request->user(), $review, $d['target'], $d['reason']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $action === 'masquer' ? 'Contenu masqué : le texte n’a pas été modifié, l’historique est conservé et les moyennes sont recalculées.' : 'Contenu rétabli : les moyennes sont recalculées.');
    }
}
