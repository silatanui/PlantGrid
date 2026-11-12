<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceTabService;
use Illuminate\Http\Request;

class WorkspaceTabController extends Controller
{
    public function destroy(Request $request, WorkspaceTabService $tabs)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'was_active' => ['nullable', 'boolean'],
        ]);

        $tabs->close($data['key']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => $request->boolean('was_active')
                    ? optional($tabs->all()->last())['url'] ?? route('dashboard')
                    : null,
            ]);
        }

        if (! $request->boolean('was_active')) {
            return back();
        }

        $remaining = $tabs->all()->last();

        return $remaining
            ? redirect($remaining['url'])
            : redirect()->route('dashboard');
    }

    public function reorder(Request $request, WorkspaceTabService $tabs)
    {
        $data = $request->validate([
            'keys' => ['required', 'array', 'min:1'],
            'keys.*' => ['required', 'string', 'max:120'],
        ]);

        $tabs->reorder($data['keys']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
