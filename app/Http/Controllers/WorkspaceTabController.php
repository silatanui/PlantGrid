<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceTabService;
use Illuminate\Http\Request;

class WorkspaceTabController extends Controller
{
    public function destroy(Request $request, WorkspaceTabService $tabs)
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:120'],
            'mode' => ['nullable', 'in:one,others,all'],
            'was_active' => ['nullable', 'boolean'],
        ]);

        $mode = $data['mode'] ?? 'one';
        $key = (string) ($data['key'] ?? '');
        $wasActive = $request->boolean('was_active');
        $closed = [];
        $redirect = null;

        if ($mode === 'all') {
            $closed = $tabs->closeAll();
            $redirect = route('dashboard');
        } elseif ($mode === 'others') {
            if ($key === '') {
                return $this->invalidKey($request);
            }
            $closed = $tabs->closeOthers($key);
            // Stay on the kept tab; client reloads when there is no redirect.
        } else {
            if ($key === '') {
                return $this->invalidKey($request);
            }
            $tabs->close($key);
            $closed = [$key];
            if ($wasActive) {
                $redirect = optional($tabs->all()->last())['url'] ?? route('dashboard');
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'mode' => $mode,
                'closed' => array_values($closed),
                'redirect' => $redirect,
            ]);
        }

        if ($redirect) {
            return redirect($redirect);
        }

        if ($mode === 'one' && ! $wasActive) {
            return back();
        }

        return back();
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

    private function invalidKey(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => 'Tab key is required.',
            ], 422);
        }

        return back();
    }
}
