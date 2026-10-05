<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\GetPublishedService;
use App\Modules\Catalog\Exceptions\ServiceNotAvailable;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('catalog.index');
    }

    public function show(string $slug, GetPublishedService $get): View|Response
    {
        try {
            $service = $get($slug);
        } catch (ServiceNotFound) {
            abort(404);
        } catch (ServiceNotAvailable) {
            return response()->view('catalog.unavailable', [], 410);
        }

        return view('catalog.show', ['service' => $service]);
    }
}
