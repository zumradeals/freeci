<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Queries\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function actions(Request $request, AuditLog $log): View
    {
        $f = $request->only(['actor', 'action', 'result', 'from', 'to', 'q']);

        return view('admin.audit', ['page' => $log->actions($f, (int) config('freeci.admin.page_size')), 'f' => $f, 'actions' => AuditLog::ACTIONS]);
    }

    public function security(Request $request, AuditLog $log): View
    {
        $f = $request->only(['type', 'from', 'to']);

        return view('admin.audit-security', ['page' => $log->security($f, (int) config('freeci.admin.page_size')), 'f' => $f, 'types' => AuditLog::SECURITY]);
    }
}
