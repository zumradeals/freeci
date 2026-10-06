<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\FavoriteQueries;
use App\Modules\Catalog\Actions\GetPublishedService;
use App\Modules\Catalog\Exceptions\ServiceNotAvailable;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Orders\Queries\ReviewQueries;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('catalog.index');
    }

    public function show(Request $request, string $slug, GetPublishedService $get, ReviewQueries $reviews, FavoriteQueries $favorites): View|Response
    {
        try {
            $service = $get($slug);
        } catch (ServiceNotFound) {
            abort(404);
        } catch (ServiceNotAvailable) {
            return response()->view('catalog.unavailable', [], 410);
        }

        $stats = $reviews->forServices([$service->id])[$service->id] ?? null;
        $service = $service->withExtras($stats, isset($favorites->marked($request->user(), 'service', [$service->id])[$service->id]));

        return view('catalog.show', ['service' => $service, 'reviews' => $reviews->pageForService($service->id, max(1, (int) $request->query('avis', 1)))->withQueryString()]);
    }
}
