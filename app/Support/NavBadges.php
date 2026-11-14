<?php

namespace App\Support;

use App\Models\DirectMessage;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;

class NavBadges
{
    /**
     * Counts and status cues for the left rail / primary tabs.
     *
     * @return array{my_cases: int, new_cases: int, messages: int, notifications: int, monitoring_status: string}
     */
    public static function for(User $user): array
    {
        return [
            'my_cases' => self::myCases($user),
            'new_cases' => self::newCases($user),
            'messages' => DirectMessage::query()->unreadFor($user)->count(),
            'notifications' => $user->unreadNotifications()->count(),
            'monitoring_status' => self::monitoringStatus($user),
        ];
    }

    /**
     * Open cases under this person (requested, owned, or partner) - not resolved/closed.
     */
    public static function myCases(User $user): int
    {
        if (! $user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            return 0;
        }

        return SupportTicket::query()
            ->involving($user)
            ->open()
            ->count();
    }

    /**
     * Unassigned new cases waiting in the ICT queue.
     */
    public static function newCases(User $user): int
    {
        if ($user->isEmployeeRequester()) {
            return 0;
        }

        if (! $user->hasAnyPermission(['tickets.view.department', 'tickets.view.all', 'tickets.assign'])) {
            return 0;
        }

        return SupportTicket::query()
            ->visibleTo($user)
            ->where('status', 'new')
            ->whereNull('assigned_to')
            ->count();
    }

    /**
     * Simulated monitoring health: normal | degraded | outage.
     */
    public static function monitoringStatus(User $user): string
    {
        if (! $user->hasPermission('monitoring.view')) {
            return 'hidden';
        }

        $scenario = SystemSetting::getValue('monitoring_scenario', 'normal') ?? 'normal';

        return in_array($scenario, ['normal', 'degraded', 'outage'], true) ? $scenario : 'normal';
    }
}
