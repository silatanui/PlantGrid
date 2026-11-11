<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\TableSort;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $sort = TableSort::from(
            $request,
            ['created_at', 'action', 'entity_type'],
            'created_at',
            'desc',
        );

        $logs = $sort->apply(
            AuditLog::query()
                ->with('user')
                ->when($request->filled('q'), function ($query) use ($request) {
                    $term = '%'.$request->string('q')->trim().'%';
                    $query->where('action', 'like', $term)->orWhere('entity_type', 'like', $term);
                })
        )
            ->paginate(20)
            ->withQueryString();

        return view('audit.index', [
            'title' => 'Audit log',
            'logs' => $logs,
            'filters' => $request->only(['q', 'sort', 'direction']),
            'sort' => $sort,
        ]);
    }
}
