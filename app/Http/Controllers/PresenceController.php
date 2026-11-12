<?php

namespace App\Http\Controllers;

use App\Services\PresenceService;
use App\Support\Format;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function heartbeat(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json([
            'ok' => true,
            'online_window_seconds' => PresenceService::ONLINE_SECONDS,
        ]);
    }

    public function live(Request $request, PresenceService $presence)
    {
        abort_unless($request->user()->hasPermission('users.view'), 403);

        $viewerId = $request->user()->id;
        $users = $presence->onlineUsers()
            ->reject(fn ($person) => $person->id === $viewerId)
            ->values();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'online_window_seconds' => PresenceService::ONLINE_SECONDS,
            'count' => $users->count(),
            'users' => $users->map(fn ($person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'role' => $person->roles->pluck('name')->join(', ') ?: '—',
                'role_label' => $person->roleLabel(),
                'department' => $person->department->name ?? '—',
                'last_seen_at' => $person->last_seen_at?->toIso8601String(),
                'last_seen_label' => Format::date($person->last_seen_at),
                'profile_url' => route('people.show', $person),
                'assign_url' => route('admin.online.assign', $person),
            ])->values(),
        ]);
    }
}
