<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    public static function groups(User $user): array
    {
        $groups = [
            'Operations' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'layout-dashboard', 'permission' => 'dashboard.view', 'primary' => true],
                ['label' => $user->isEmployeeRequester() ? 'My cases' : 'Cases', 'route' => 'helpdesk.index', 'pattern' => 'helpdesk.*', 'icon' => 'life-buoy', 'permission' => 'tickets.view.own', 'primary' => true],
                ['label' => 'Messages', 'route' => 'messages.index', 'pattern' => 'messages.*', 'icon' => 'message-square', 'permission' => 'dashboard.view', 'primary' => true],
                ['label' => 'Documentation', 'route' => 'docs.index', 'pattern' => 'docs.*', 'icon' => 'book-open', 'permission' => 'dashboard.view', 'primary' => true],
                ['label' => 'Maintenance', 'route' => 'maintenance.index', 'pattern' => 'maintenance.*', 'icon' => 'wrench', 'permission' => 'maintenance.view'],
                ['label' => 'Monitoring', 'route' => 'monitoring.index', 'pattern' => 'monitoring.*', 'icon' => 'activity', 'permission' => 'monitoring.view'],
            ],
            'Infrastructure' => [
                ['label' => 'Assets', 'route' => 'assets.index', 'pattern' => 'assets.*', 'icon' => 'boxes', 'permission' => 'assets.view'],
                ['label' => 'Network', 'route' => 'network.index', 'pattern' => 'network.*', 'icon' => 'network', 'permission' => 'network.view'],
                ['label' => 'Servers', 'route' => 'servers.index', 'pattern' => 'servers.*', 'icon' => 'server', 'permission' => 'servers.view'],
                ['label' => 'Production', 'route' => 'production.index', 'pattern' => 'production.*', 'icon' => 'factory', 'permission' => 'production.view'],
            ],
            'Assurance' => [
                ['label' => 'Security', 'route' => 'security.index', 'pattern' => 'security.*', 'icon' => 'shield', 'permission' => 'security.view'],
                ['label' => 'Backups', 'route' => 'backups.index', 'pattern' => 'backups.*', 'icon' => 'hard-drive', 'permission' => 'backups.view'],
                ['label' => 'Reports', 'route' => 'reports.index', 'pattern' => 'reports.*', 'icon' => 'bar-chart-3', 'permission' => 'reports.view'],
                ['label' => 'Performance', 'route' => 'performance.index', 'pattern' => 'performance.*', 'icon' => 'gauge', 'permission' => 'reports.view'],
            ],
            'Administration' => [
                ['label' => 'Administration', 'route' => 'admin.index', 'pattern' => 'admin.index', 'icon' => 'settings', 'permission' => 'users.view'],
                ['label' => 'Online users', 'route' => 'admin.online', 'pattern' => 'admin.online', 'icon' => 'radio', 'permission' => 'users.view'],
                ['label' => 'Audit log', 'route' => 'audit.index', 'pattern' => 'audit.*', 'icon' => 'scroll-text', 'permission' => 'audit.view'],
            ],
        ];

        $visible = [];

        foreach ($groups as $heading => $items) {
            $allowed = array_values(array_filter($items, fn (array $item) => self::allowed($user, $item)));

            if ($allowed !== []) {
                $visible[$heading] = $allowed;
            }
        }

        return $visible;
    }

    public static function primary(User $user): array
    {
        $items = [];

        foreach (self::groups($user) as $group) {
            foreach ($group as $item) {
                if (! empty($item['primary'])) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    private static function allowed(User $user, array $item): bool
    {
        if ($item['permission'] === 'tickets.view.own') {
            return $user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']);
        }

        return $user->hasPermission($item['permission']);
    }
}
