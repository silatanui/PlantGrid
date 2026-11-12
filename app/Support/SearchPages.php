<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchPages
{
    /**
     * Navigational destinations that should appear in global search.
     *
     * @return list<array{title: string, summary: string, keywords: list<string>, url: string, permission: string|list<string>|null}>
     */
    public static function catalog(User $user): array
    {
        return [
            [
                'title' => 'My cases',
                'summary' => 'Cases you opened and are following.',
                'keywords' => ['my cases', 'my tickets', 'cases I opened', 'my requests', 'self service'],
                'url' => route('helpdesk.mine'),
                'permission' => ['tickets.view.own', 'tickets.view.department', 'tickets.view.all'],
            ],
            [
                'title' => 'Assigned to me',
                'summary' => 'Cases currently owned by you.',
                'keywords' => ['assigned to me', 'my queue', 'my ownership', 'owned cases'],
                'url' => route('helpdesk.assigned'),
                'permission' => 'tickets.update',
            ],
            [
                'title' => 'Case queue',
                'summary' => 'Open ICT case queue across the plant.',
                'keywords' => ['case queue', 'all cases', 'helpdesk', 'tickets', 'open cases'],
                'url' => route('helpdesk.index', ['scope' => 'queue']),
                'permission' => ['tickets.view.department', 'tickets.view.all', 'tickets.update'],
            ],
            [
                'title' => 'New case',
                'summary' => 'Open a new helpdesk case.',
                'keywords' => ['new case', 'create case', 'report issue', 'open ticket'],
                'url' => route('helpdesk.create'),
                'permission' => 'tickets.create',
            ],
            [
                'title' => 'Dashboard',
                'summary' => 'Your PlantGrid home console.',
                'keywords' => ['dashboard', 'home', 'overview'],
                'url' => route('dashboard'),
                'permission' => 'dashboard.view',
            ],
            [
                'title' => 'Documentation',
                'summary' => 'Guides for cases, ownership, notes and SLA.',
                'keywords' => ['docs', 'documentation', 'guides', 'how to'],
                'url' => route('docs.index'),
                'permission' => 'dashboard.view',
            ],
        ];
    }

    /**
     * @return Collection<int, array{title: string, summary: string, url: string}>
     */
    public static function search(User $user, string $term, int $limit = 8): Collection
    {
        $needle = Str::lower(trim($term));
        if ($needle === '') {
            return collect();
        }

        return collect(self::catalog($user))
            ->filter(function (array $page) use ($user, $needle) {
                if (! self::allowed($user, $page['permission'] ?? null)) {
                    return false;
                }

                if (str_contains(Str::lower($page['title']), $needle)) {
                    return true;
                }

                if (str_contains(Str::lower($page['summary']), $needle)) {
                    return true;
                }

                foreach ($page['keywords'] as $keyword) {
                    if (str_contains(Str::lower($keyword), $needle) || str_contains($needle, Str::lower($keyword))) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (array $page) => [
                'title' => $page['title'],
                'summary' => $page['summary'],
                'url' => $page['url'],
            ])
            ->values()
            ->take($limit);
    }

    public static function isMyCasesIntent(string $term): bool
    {
        $needle = Str::lower(trim($term));

        return $needle !== '' && (
            str_contains($needle, 'my case')
            || str_contains($needle, 'my ticket')
            || str_contains($needle, 'cases i opened')
            || str_contains($needle, 'my request')
        );
    }

    private static function allowed(User $user, string|array|null $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        if (is_array($permission)) {
            return $user->hasAnyPermission($permission);
        }

        return $user->hasPermission($permission);
    }
}
