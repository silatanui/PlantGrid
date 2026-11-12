<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class PresenceService
{
    /** How long after the last heartbeat a user still counts as online. */
    public const ONLINE_SECONDS = 60;

    /** Kept for older views/docs that still speak in minutes. */
    public const ONLINE_MINUTES = 1;

    public function threshold()
    {
        return now()->subSeconds(self::ONLINE_SECONDS);
    }

    public function isOnline(User $user): bool
    {
        return $user->status === 'active'
            && $user->last_seen_at
            && $user->last_seen_at->gte($this->threshold());
    }

    /**
     * @return Collection<int, User>
     */
    public function onlineUsers(): Collection
    {
        return User::query()
            ->with(['roles:id,name', 'department:id,name,code'])
            ->where('status', 'active')
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $this->threshold())
            ->orderByDesc('last_seen_at')
            ->get();
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $users
     * @return array<int, bool>
     */
    public function onlineMap(iterable $users): array
    {
        $map = [];
        foreach ($users as $user) {
            $map[$user->id] = $this->isOnline($user);
        }

        return $map;
    }
}
