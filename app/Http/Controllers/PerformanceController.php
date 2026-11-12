<?php

namespace App\Http\Controllers;

use App\Services\WorkerPerformanceService;
use App\Support\Csv;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function __construct(private WorkerPerformanceService $performance) {}

    public function index(Request $request)
    {
        $board = $this->performance->board($request->all());
        $presets = $this->performance->rangePresets();
        $activeRange = collect($presets)->first(function (array $preset) use ($board) {
            return $preset['from'] === $board['from']->toDateString()
                && $preset['to'] === $board['to']->toDateString();
        })['id'] ?? 'custom';

        return view('performance.index', [
            'title' => 'Worker performance',
            'board' => $board,
            'charts' => $this->performance->charts($board),
            'rangePresets' => $presets,
            'activeRange' => $activeRange,
            'canExport' => $request->user()->hasPermission('reports.export'),
        ]);
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->hasPermission('reports.export'), 403);

        $board = $this->performance->board($request->all());

        return Csv::download(
            'plantgrid-worker-performance.csv',
            $this->performance->exportHeaders(),
            $this->performance->exportRows($board)
        );
    }
}
