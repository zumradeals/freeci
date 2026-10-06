<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\ReviewReconciliation;
use App\Modules\Admin\Queries\ReconciliationList;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(Request $request, ReconciliationList $list): View
    {
        $status = $request->query('statut') === 'resolved' ? 'resolved' : 'open';

        return view('admin.payments', ['page' => $list->page($status, (int) config('freeci.admin.page_size')), 'status' => $status, 'open' => $list->counts()]);
    }

    public function review(Request $request, ReviewReconciliation $review, string $id): RedirectResponse
    {
        $d = $request->validate(['note' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $review($request->user(), $id, $d['note']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Dossier marqué comme examiné. Aucune opération financière n’a été exécutée ni relancée.');
    }
}
