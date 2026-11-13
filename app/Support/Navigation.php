<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    public static function groups(User $user): array
    {
        $caseItems = [
            [
                'label' => 'My cases',
                'route' => 'helpdesk.index',
                'params' => ['scope' => 'mine', 'status' => 'open'],
                'pattern' => 'helpdesk.*',
                'icon' => 'life-buoy',
                'permission' => 'tickets.view.own',
                'primary' => true,
                'badge' => 'my_cases',
                'active_when' => 'my_cases',
            ],
        ];

        if (! $user->isEmployeeRequester()) {
            $caseItems[] = [
                'label' => 'New cases',
                'route' => 'helpdesk.index',
                'params' => ['status' => 'new', 'unassigned' => 1],
                'pattern' => 'helpdesk.index',
                'icon' => 'inbox',
                'permission' => 'tickets.view.own',
                'primary' => false,
                'badge' => 'new_cases',
                'active_when' => 'new_cases',
            ];
        }

        $groups = [
            'Operations' => array_merge(
                [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'pattern' => 'dashboard', 'icon' => 'layout-dashboard', 'permission' => 'dashboard.view', 'primary' => true],
                ],
                $caseItems,
                [
                    ['label' => 'Messages', 'route' => 'messages.index', 'pattern' => 'messages.*', 'icon' => 'message-square', 'permission' => 'dashboard.view', 'primary' => true, 'badge' => 'messages'],
                    ['label' => 'Documentation', 'route' => 'docs.index', 'pattern' => 'docs.*', 'icon' => 'book-open', 'permission' => 'dashboard.view', 'primary' => true],
                    ['label' => 'Maintenance', 'route' => 'maintenance.index', 'pattern' => 'maintenance.*', 'icon' => 'wrench', 'permission' => 'maintenance.view'],
                    [
                        'label' => 'Monitoring',
                        'route' => 'monitoring.index',
                        'pattern' => 'monitoring.*',
                        'icon' => 'activity',
                        'permission' => 'monitoring.view',
                        'status' => 'monitoring',
                    ],
                ]
            ),
            'Infrastructure' => [
                ['label' => 'Assets', 'route' => 'assets.index', 'pattern' => 'assets.*', 'icon' => 'boxes', 'permission' => 'assets.view'],
                ['label' => 'Plant map', 'route' => 'locations.index', 'pattern' => 'locations.*', 'icon' => 'map', 'permission' => 'assets.view'],
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

    public static function isActive(array $item): bool
    {
        $when = $item['active_when'] ?? null;

        if ($when === 'my_cases') {
            if (! request()->routeIs('helpdesk.*')) {
                return false;
            }
            if (request()->routeIs('helpdesk.show')) {
                return true;
            }
            if (request()->routeIs('helpdesk.create')) {
                return false;
            }

            $scope = request('scope');
            $status = request('status');

            if ($status === 'new' && ($scope === null || $scope === '' || $scope === 'queue')) {
                return false;
            }

            return in_array($scope, [null, '', 'mine', 'assigned'], true);
        }

        if ($when === 'new_cases') {
            return request()->routeIs('helpdesk.index')
                && request('status') === 'new'
                && request()->boolean('unassigned')
                && ! in_array(request('scope'), ['mine', 'assigned'], true);
        }

        return request()->routeIs($item['pattern']);
    }

    private static function allowed(User $user, array $item): bool
    {
        if ($item['permission'] === 'tickets.view.own') {
            return $user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all']);
        }

        if (($item['route'] ?? '') === 'locations.index') {
            return $user->hasAnyPermission(['assets.view', 'network.view']);
        }

        return $user->hasPermission($item['permission']);
    }
}
