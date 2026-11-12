<?php

namespace App\Support;

use App\Models\DirectMessage;
use App\Models\MonitoringAlert;
use App\Models\SupportTicket;
use App\Models\User;

class NavBadges
{
    /**
     * Counts shown on the left rail / primary tabs (mirrors header live badges).
     *
     * @return array{cases: int, messages: int, notifications: int, alerts: int}
     */
    public static function for(User $user): array
    {
        return [
            'cases' => self::cases($user),
            'messages' => DirectMessage::query()->unreadFor($user)->count(),
            'notifications' => $user->unreadNotifications()->count(),
            'alerts' => self::alerts($user),
        ];
    }

    public static function cases(User $user): int
    {
        if (! $user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            return 0;
        }

        $visible = SupportTicket::query()->visibleTo($user);

        if ($user->isEmployeeRequester()) {
            return (clone $visible)->open()->count();
        }

        if ($user->hasPermission('tickets.assign')) {
            return (clone $visible)->where('status', 'new')->whereNull('assigned_to')->count();
        }

        return SupportTicket::query()
            ->involving($user)
            ->open()
            ->count();
    }

    public static function alerts(User $user): int
    {
        if (! $user->hasPermission('monitoring.view') || $user->isEmployeeRequester()) {
            return 0;
        }

        return MonitoringAlert::query()
            ->whereIn('status', ['open', 'acknowledged'])
            ->count();
    }
}
