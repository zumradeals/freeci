<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\CsvExports;
use App\Modules\Admin\Queries\Statistics;
use App\Modules\Admin\Support\StatsPeriod;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Statistiques d'administration et exports CSV (F-17) : administrateurs seulement. */
class StatisticsController extends Controller
{
    private function live(Request $request): bool
    {
        return $request->query('env', $request->input('env', 'reel')) !== 'test';
    }

    public function index(Request $request, Statistics $stats): View
    {
        $p = StatsPeriod::fromInput($request->query());
        $live = $this->live($request);

        return view('admin.statistics', ['s' => $stats($p, $live), 'p' => $p, 'live' => $live, 'presets' => StatsPeriod::PRESETS]);
    }

    public function exports(Request $request): View
    {
        $p = StatsPeriod::fromInput($request->query());

        return view('admin.exports', ['p' => $p, 'live' => $this->live($request), 'datasets' => CsvExports::catalogue(), 'presets' => StatsPeriod::PRESETS, 'max' => CsvExports::maxRows()]);
    }

    public function download(Request $request, string $dataset, CsvExports $exports): StreamedResponse|RedirectResponse
    {
        $p = StatsPeriod::fromInput($request->all());
        $live = $this->live($request);
        $back = route('admin.exports', $p->query() + ['env' => $live ? 'reel' : 'test']);
        try {
            [$file, $write] = $exports->prepare($request->user(), $dataset, $p, $live);
        } catch (ValidationException) {
            abort(404);
        } catch (ModerationDenied|\DomainException $e) {
            return redirect($back)->with('error', $e->getMessage());
        }

        return response()->streamDownload($write, $file, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
