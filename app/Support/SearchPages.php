<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SearchPages
{
    public const OPEN_STATUSES = ['new', 'assigned', 'in_progress', 'pending'];

    /**
     * Navigational destinations that should appear in global search.
     *
     * @return list<array{title: string, summary: string, keywords: list<string>, url: string, permission: string|list<string>|null}>
     */
    public static function catalog(User $user): array
    {
        $ticketPermission = ['tickets.view.own', 'tickets.view.department', 'tickets.view.all'];

        return [
            [
                'title' => 'My cases',
                'summary' => 'Cases you requested, own, or partner on.',
                'keywords' => ['my cases', 'my tickets', 'cases i opened', 'my requests', 'self service', 'assigned to me'],
                'url' => route('helpdesk.index', ['scope' => 'mine']),
                'permission' => $ticketPermission,
            ],
            [
                'title' => 'My open cases',
                'summary' => 'Your active cases (requested, owned, or partnered) that are not resolved or closed.',
                'keywords' => ['my open cases', 'my open tickets', 'open cases', 'active cases', 'my active cases'],
                'url' => route('helpdesk.index', ['scope' => 'mine', 'status' => 'open']),
                'permission' => $ticketPermission,
            ],
            [
                'title' => 'My closed cases',
                'summary' => 'Your closed cases (requested, owned, or partnered).',
                'keywords' => ['my closed cases', 'my closed tickets', 'closed cases'],
                'url' => route('helpdesk.index', ['scope' => 'mine', 'status' => 'closed']),
                'permission' => $ticketPermission,
            ],
            [
                'title' => 'My resolved cases',
                'summary' => 'Your resolved cases waiting for closure.',
                'keywords' => ['my resolved cases', 'my resolved tickets', 'resolved cases'],
                'url' => route('helpdesk.index', ['scope' => 'mine', 'status' => 'resolved']),
                'permission' => $ticketPermission,
            ],
            [
                'title' => 'My pending cases',
                'summary' => 'Your cases waiting on more information or an external step.',
                'keywords' => ['my pending cases', 'my pending tickets', 'pending cases'],
                'url' => route('helpdesk.index', ['scope' => 'mine', 'status' => 'pending']),
                'permission' => $ticketPermission,
            ],
            [
                'title' => 'My in-progress cases',
                'summary' => 'Your cases that ICT is actively working.',
                'keywords' => ['my in progress cases', 'my in-progress cases', 'in progress cases', 'working cases'],
                'url' => route('helpdesk.index', ['scope' => 'mine', 'status' => 'in_progress']),
                'permission' => $ticketPermission,
            ],
            [
                'title' => 'Assigned to me',
                'summary' => 'Cases currently owned by you.',
                'keywords' => ['assigned to me', 'my queue', 'my ownership', 'owned cases', 'my assigned cases'],
                'url' => route('helpdesk.index', ['scope' => 'assigned']),
                'permission' => 'tickets.update',
            ],
            [
                'title' => 'My assigned open cases',
                'summary' => 'Open cases you currently own.',
                'keywords' => ['my assigned open cases', 'assigned open cases', 'open assigned'],
                'url' => route('helpdesk.index', ['scope' => 'assigned', 'status' => 'open']),
                'permission' => 'tickets.update',
            ],
            [
                'title' => 'Case queue',
                'summary' => 'Open ICT case queue across the plant.',
                'keywords' => ['case queue', 'all cases', 'helpdesk', 'tickets'],
                'url' => route('helpdesk.index', ['scope' => 'queue']),
                'permission' => ['tickets.view.department', 'tickets.view.all', 'tickets.update'],
            ],
            [
                'title' => 'Open case queue',
                'summary' => 'Active cases still in the ICT queue.',
                'keywords' => ['open case queue', 'open queue', 'active queue'],
                'url' => route('helpdesk.index', ['scope' => 'queue', 'status' => 'open']),
                'permission' => ['tickets.view.department', 'tickets.view.all', 'tickets.update'],
            ],
            [
                'title' => 'Plant map',
                'summary' => 'Interactive Northridge floor plan with locations and installed devices.',
                'keywords' => ['plant map', 'floor plan', 'main entrance', 'location map', 'where is'],
                'url' => route('locations.index'),
                'permission' => ['assets.view', 'network.view'],
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
            ->filter(fn (array $page) => self::allowed($user, $page['permission'] ?? null))
            ->map(function (array $page) use ($needle) {
                $score = self::matchScore($page, $needle);

                return $score > 0 ? [...$page, 'score' => $score] : null;
            })
            ->filter()
            ->sortByDesc('score')
            ->map(fn (array $page) => [
                'title' => $page['title'],
                'summary' => $page['summary'],
                'url' => $page['url'],
            ])
            ->values()
            ->take($limit);
    }

    /**
     * @return array{scope: string, status: ?string, label: string}|null
     */
    public static function caseListIntent(string $term): ?array
    {
        $needle = Str::lower(trim($term));
        if ($needle === '') {
            return null;
        }

        $isMine = str_contains($needle, 'my case')
            || str_contains($needle, 'my ticket')
            || str_contains($needle, 'cases i opened')
            || str_contains($needle, 'my request')
            || str_contains($needle, 'my open')
            || str_contains($needle, 'my closed')
            || str_contains($needle, 'my resolved')
            || str_contains($needle, 'my pending')
            || str_contains($needle, 'my in progress')
            || str_contains($needle, 'my in-progress');

        $isAssigned = str_contains($needle, 'assigned to me')
            || str_contains($needle, 'my assigned')
            || str_contains($needle, 'owned cases')
            || (str_contains($needle, 'assigned') && str_contains($needle, 'case'));

        if (! $isMine && ! $isAssigned) {
            return null;
        }

        $scope = $isAssigned && ! $isMine ? 'assigned' : 'mine';
        if ($isAssigned && (str_contains($needle, 'assigned') || str_contains($needle, 'owned'))) {
            $scope = 'assigned';
        }
        if ($isMine && ! str_contains($needle, 'assigned') && ! str_contains($needle, 'owned')) {
            $scope = 'mine';
        }

        $status = null;
        $label = $scope === 'assigned' ? 'Assigned to me' : 'My cases';

        if (str_contains($needle, 'open') || str_contains($needle, 'active')) {
            $status = 'open';
            $label = $scope === 'assigned' ? 'My assigned open cases' : 'My open cases';
        } elseif (str_contains($needle, 'closed') || str_contains($needle, 'close')) {
            $status = 'closed';
            $label = $scope === 'assigned' ? 'My assigned closed cases' : 'My closed cases';
        } elseif (str_contains($needle, 'resolved') || str_contains($needle, 'resolve')) {
            $status = 'resolved';
            $label = $scope === 'assigned' ? 'My assigned resolved cases' : 'My resolved cases';
        } elseif (str_contains($needle, 'pending')) {
            $status = 'pending';
            $label = $scope === 'assigned' ? 'My assigned pending cases' : 'My pending cases';
        } elseif (str_contains($needle, 'in progress') || str_contains($needle, 'in-progress')) {
            $status = 'in_progress';
            $label = $scope === 'assigned' ? 'My assigned in-progress cases' : 'My in-progress cases';
        }

        return [
            'scope' => $scope,
            'status' => $status,
            'label' => $label,
        ];
    }

    public static function isMyCasesIntent(string $term): bool
    {
        return self::caseListIntent($term) !== null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\SupportTicket>
     */
    public static function applyStatusFilter($query, ?string $status)
    {
        $status = trim((string) $status);
        if ($status === '') {
            return $query;
        }

        if ($status === 'open') {
            return $query->whereIn('status', self::OPEN_STATUSES);
        }

        return $query->where('status', $status);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'closed' => 'Closed',
            'resolved' => 'Resolved',
            'pending' => 'Pending',
            'in_progress' => 'In progress',
            'assigned' => 'Assigned',
            'new' => 'New',
            default => $status ? Labels::status($status) : 'Any',
        };
    }

    /**
     * @param  array{title: string, summary: string, keywords: list<string>}  $page
     */
    private static function matchScore(array $page, string $needle): int
    {
        $title = Str::lower($page['title']);
        $summary = Str::lower($page['summary']);
        $score = 0;

        if ($title === $needle) {
            $score += 200;
        } elseif (str_contains($title, $needle)) {
            $score += 120;
        } elseif (str_contains($needle, $title)) {
            $score += 90;
        }

        if (str_contains($summary, $needle)) {
            $score += 20;
        }

        foreach ($page['keywords'] as $keyword) {
            $keyword = Str::lower($keyword);
            if ($keyword === $needle) {
                $score += 150;
            } elseif (str_contains($keyword, $needle)) {
                $score += 40 + mb_strlen($needle);
            } elseif (str_contains($needle, $keyword)) {
                $score += 30 + mb_strlen($keyword);
            }
        }

        return $score;
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
