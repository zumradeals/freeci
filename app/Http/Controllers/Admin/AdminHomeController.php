<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Queries\AdminDashboard;
use Illuminate\View\View;

class AdminHomeController extends Controller
{
    public function __invoke(AdminDashboard $dashboard): View
    {
        return view('admin.home', ['d' => $dashboard()]);
    }
}
