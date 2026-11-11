<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class PresenceService
{
    public const ONLINE_MINUTES = 5;

    public function threshold()
    {
        return now()->subMinutes(self::ONLINE_MINUTES);
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
