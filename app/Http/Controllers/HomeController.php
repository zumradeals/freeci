<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\ListCategories;
use App\Modules\Catalog\Actions\ListPublishedServices;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(ListCategories $categories, ListPublishedServices $recent): View
    {
        return view('pages.home', [
            'categories' => $categories->withServiceCounts(),
            'services' => $recent(8),
        ]);
    }
}
