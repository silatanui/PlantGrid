<?php

namespace App\Services;

use App\Models\MonitoringAlert;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Collection;

class ShellPanelService
{
    public function left(User $user): array
    {
        return [
            'groups' => Navigation::groups($user),
            'recents' => $this->recentCases($user, 5),
            'shortcuts' => $this->shortcuts($user),
        ];
    }

    public function right(User $user): array
    {
        $employee = $user->isEmployeeRequester();

        return [
            'guidance' => $this->guidance($user, $employee),
            'actions' => $this->actions($user, $employee),
            'activity' => $this->activity($user, $employee),
            'alerts' => $employee || ! $user->hasPermission('monitoring.view')
                ? collect()
                : MonitoringAlert::query()
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->latest('triggered_at')
                    ->limit(4)
                    ->get(),
            'stats' => $this->stats($user, $employee),
        ];
    }

    private function shortcuts(User $user): array
    {
        // Keep shortcuts to actions only — module destinations live under Modules.
        $items = [];

        if ($user->hasPermission('tickets.create')) {
            $items[] = [
                'label' => 'New case',
                'route' => 'helpdesk.create',
                'icon' => 'plus',
            ];
        }

        if ($user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $items[] = [
                'label' => 'My open cases',
                'route' => 'helpdesk.index',
                'icon' => 'folder-open',
                'params' => ['scope' => 'mine', 'status' => 'open'],
                'badge' => 'my_cases',
            ];

            if (! $user->isEmployeeRequester()) {
                $items[] = [
                    'label' => 'New cases',
                    'route' => 'helpdesk.index',
                    'icon' => 'inbox',
                    'params' => ['status' => 'new', 'unassigned' => 1],
                    'badge' => 'new_cases',
                ];
            }
        }

        return $items;
    }

    private function guidance(User $user, bool $employee): array
    {
        if ($employee) {
            return [
                'title' => 'Guidance',
                'headline' => 'Report plant ICT issues here',
                'body' => 'Open a case for hardware, apps or network problems on the floor. ICT assigns and resolves; you follow progress under My cases.',
                'steps' => [
                    'Open New case with a clear subject',
                    'Link the asset or application if you know it',
                    'Watch status changes on My cases',
                ],
            ];
        }

        if ($user->hasPermission('tickets.assign')) {
            $waiting = SupportTicket::query()->where('status', 'new')->whereNull('assigned_to')->count();

            return [
                'title' => 'Guidance',
                'headline' => 'Keep the case queue moving',
                'body' => $waiting > 0
                    ? $waiting.' new '.($waiting === 1 ? 'case is' : 'cases are').' waiting for assignment.'
                    : 'No unassigned new cases right now. Review in-progress work and due dates.',
                'steps' => [
                    'Assign new cases to a technician',
                    'Update status when work starts or stalls',
                    'Add resolution notes before closing',
                ],
            ];
        }

        return [
            'title' => 'Guidance',
            'headline' => 'Work from your assigned queue',
            'body' => 'Update case status as you progress and leave notes the requester can see when appropriate.',
            'steps' => [
                'Open Assigned to me',
                'Move cases to In progress',
                'Resolve with clear notes',
            ],
        ];
    }

    private function actions(User $user, bool $employee): array
    {
        $actions = [];

        if ($user->hasPermission('tickets.create')) {
            $actions[] = ['label' => 'New case', 'route' => 'helpdesk.create', 'tone' => 'primary'];
        }

        if ($user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $actions[] = [
                'label' => $employee ? 'View my cases' : 'Open case queue',
                'route' => 'helpdesk.index',
                'tone' => 'secondary',
            ];
        }

        if ($user->hasPermission('assets.view')) {
            $actions[] = ['label' => 'Asset register', 'route' => 'assets.index', 'tone' => 'secondary'];
        }

        if ($user->hasPermission('network.view')) {
            $actions[] = ['label' => 'Network map', 'route' => 'network.index', 'tone' => 'secondary'];
        }

        if ($user->hasPermission('monitoring.view')) {
            $actions[] = ['label' => 'Simulated alerts', 'route' => 'monitoring.index', 'tone' => 'secondary'];
        }

        return $actions;
    }

    private function activity(User $user, bool $employee): Collection
    {
        $mine = \App\Support\CaseActivity::forUserRail($user, 8);
        if ($mine->isNotEmpty()) {
            return $mine;
        }

        // Fallback when the signed-in user has no audited actions yet.
        $tickets = SupportTicket::query()
            ->visibleTo($user)
            ->with(['requester', 'assignee'])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return $tickets->map(function (SupportTicket $ticket) use ($employee) {
            $actor = $employee
                ? ($ticket->assignee->name ?? 'ICT')
                : ($ticket->requester->name ?? 'Requester');

            return [
                'title' => $ticket->subject,
                'meta' => $ticket->ticket_number.' · '.$actor.' · '.ucwords(str_replace('_', ' ', $ticket->status)),
                'href' => route('helpdesk.show', $ticket),
                'when' => optional($ticket->updated_at)->diffForHumans(),
            ];
        });
    }

    private function stats(User $user, bool $employee): array
    {
        if (! $user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            return [];
        }

        $tickets = SupportTicket::query()->visibleTo($user);

        if ($employee) {
            return [
                ['label' => 'My open', 'value' => (clone $tickets)->open()->count()],
                ['label' => 'Resolved', 'value' => (clone $tickets)->where('status', 'resolved')->count()],
            ];
        }

        return [
            ['label' => 'Open queue', 'value' => (clone $tickets)->open()->count()],
            ['label' => 'Unassigned', 'value' => (clone $tickets)->where('status', 'new')->whereNull('assigned_to')->count()],
        ];
    }

    private function recentCases(User $user, int $limit): Collection
    {
        if (! $user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            return collect();
        }

        return SupportTicket::query()
            ->visibleTo($user)
            ->latest('updated_at')
            ->limit($limit)
            ->get(['id', 'ticket_number', 'subject', 'status', 'priority']);
    }
}
