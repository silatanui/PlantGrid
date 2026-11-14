<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceRecord;
use App\Models\MonitoringAlert;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Support\DocumentationCatalog;
use App\Support\Labels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiContextService
{
    public function snapshot(?User $user = null, string $message = ''): string
    {
        $user ??= auth()->user();
        if (! $user) {
            return 'No signed-in user context.';
        }

        $user->loadMissing('roles:id,name');

        $tickets = SupportTicket::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'department:id,name'])
            ->latest('reported_at')
            ->get(['id', 'ticket_number', 'subject', 'priority', 'status', 'assigned_to', 'department_id', 'due_at', 'reported_at']);

        $byStatus = $tickets->groupBy('status')->map->count()->all();
        $open = $tickets->filter(fn (SupportTicket $ticket) => ! in_array($ticket->status, ['resolved', 'closed'], true));
        $openCritical = $open->whereIn('priority', ['critical', 'high']);
        $done = $tickets->whereIn('status', ['resolved', 'closed']);

        $alerts = MonitoringAlert::query()
            ->whereIn('status', ['open', 'acknowledged'])
            ->latest('triggered_at')
            ->limit(8)
            ->get(['id', 'message', 'severity', 'status', 'triggered_at']);

        $assets = Asset::query()
            ->with(['location:id,name', 'department:id,name', 'category:id,name'])
            ->orderBy('asset_tag')
            ->get(['id', 'asset_tag', 'name', 'status', 'condition', 'location_id', 'department_id', 'asset_category_id', 'manufacturer', 'model']);

        $assetStatusCounts = $assets->groupBy('status')->map->count()->all();
        $assetsAtRisk = $assets->whereIn('status', ['under_maintenance', 'damaged', 'lost', 'spare', 'retired']);
        $inactiveAssets = $assets->where('status', '!=', 'active');

        $devices = NetworkDevice::query()
            ->with(['asset:id,asset_tag,name,status', 'location:id,name'])
            ->orderBy('hostname')
            ->get(['id', 'asset_id', 'hostname', 'device_type', 'management_ip', 'location_id', 'status', 'manufacturer', 'model']);

        $deviceStatusCounts = $devices->groupBy('status')->map->count()->all();

        $servers = Server::query()
            ->orderBy('hostname')
            ->get(['hostname', 'server_role', 'environment', 'status', 'ip_address_id']);

        $maintenanceDue = MaintenanceRecord::query()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get(['id', 'subject', 'priority', 'status', 'scheduled_at']);

        $people = User::query()
            ->with(['roles:id,name', 'department:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department_id', 'status']);

        $departments = Department::query()->where('status', 'active')->orderBy('name')->get(['name', 'code']);
        $categories = TicketCategory::query()->orderBy('name')->pluck('name');

        $lines = [
            'LIVE DEMO SNAPSHOT (fictional Northridge data; answer from this when relevant)',
            'Viewer: '.$user->name.' <'.$user->email.'>',
            'Roles: '.$user->roles->pluck('name')->filter()->implode(', ') ?: 'none',
            'Permissions note: tickets.create='.($user->hasPermission('tickets.create') ? 'yes' : 'no'),
            'Departments: '.$departments->map(fn (Department $d) => $d->name.' ['.$d->code.']')->implode('; '),
            'Ticket categories: '.$categories->implode('; '),
            'Cases visible to this user: '.$tickets->count().' total',
            'Case counts by status: '.$this->formatCounts($byStatus),
            'Open cases: '.$open->count().' (high/critical: '.$openCritical->count().')',
            'Resolved+closed: '.$done->count(),
            'All cases solved?: '.($open->isEmpty() ? 'YES — no open cases in this viewer scope' : 'NO — '.$open->count().' still open'),
            'Assets total: '.$assets->count().' · by status: '.$this->formatCounts($assetStatusCounts),
            'All assets active?: '.($inactiveAssets->isEmpty() ? 'YES' : 'NO — '.$inactiveAssets->count().' not active'),
            'Network devices total: '.$devices->count().' · by status: '.$this->formatCounts($deviceStatusCounts),
            'All network devices active?: '.($devices->where('status', '!=', 'active')->isEmpty() ? 'YES' : 'NO'),
            'Open monitoring alerts: '.$alerts->count(),
            'Scheduled/in-progress maintenance: '.$maintenanceDue->count(),
            'People directory size: '.$people->count().' (demo contacts: name, email, job title, phone, mobile, extension, address, role, department)',
            '',
            'People directory (name · email · role · department · status):',
            ...$people->map(fn (User $person) => $this->personLine($person))->all(),
            '',
            'Asset register (tag · name · status · condition · location):',
            ...$assets->map(fn (Asset $asset) => sprintf(
                '- %s · %s · %s · %s · %s',
                $asset->asset_tag,
                $asset->name,
                Labels::status((string) $asset->status),
                Labels::status((string) ($asset->condition ?: 'n/a')),
                $asset->location?->name ?? 'n/a'
            ))->all(),
            '',
            'Network devices (hostname · asset tag · type · IP · status · location):',
            ...$devices->map(fn (NetworkDevice $device) => sprintf(
                '- %s · %s · %s · %s · %s · %s',
                $device->hostname,
                $device->asset?->asset_tag ?? 'n/a',
                Labels::status((string) $device->device_type),
                $device->management_ip ?: 'n/a',
                Labels::status((string) $device->status),
                $device->location?->name ?? 'n/a'
            ))->all(),
            '',
            'Servers (hostname · role · env · status):',
            ...($servers->isEmpty()
                ? ['- none']
                : $servers->map(fn (Server $server) => sprintf(
                    '- %s · %s · %s · %s',
                    $server->hostname,
                    $server->server_role ?: 'n/a',
                    $server->environment ?: 'n/a',
                    Labels::status((string) $server->status)
                ))->all()),
            '',
            'Open cases (newest first):',
            ...$this->ticketLines($open->take(12)->values()),
            '',
            'Recently resolved/closed cases:',
            ...$this->ticketLines($done->take(6)->values()),
            '',
            'Active monitoring alerts:',
            ...($alerts->isEmpty()
                ? ['- none']
                : $alerts->map(fn ($alert) => sprintf(
                    '- %s · %s · %s · %s',
                    $alert->message,
                    Labels::status((string) $alert->severity),
                    Labels::status((string) $alert->status),
                    optional($alert->triggered_at)?->toDateTimeString() ?? 'n/a'
                ))->all()),
            '',
            'Assets needing attention:',
            ...($assetsAtRisk->isEmpty()
                ? ['- none']
                : $assetsAtRisk->values()->map(fn (Asset $asset) => sprintf(
                    '- %s · %s · %s',
                    $asset->asset_tag,
                    $asset->name,
                    Labels::status((string) $asset->status)
                ))->all()),
            '',
            'Upcoming/active maintenance:',
            ...($maintenanceDue->isEmpty()
                ? ['- none']
                : $maintenanceDue->map(fn ($row) => sprintf(
                    '- #%d · %s · %s · %s · due %s',
                    $row->id,
                    $row->subject,
                    Labels::status((string) $row->priority),
                    Labels::status((string) $row->status),
                    optional($row->scheduled_at)?->toDateTimeString() ?? 'unscheduled'
                ))->all()),
            '',
            'Module paths: Dashboard, Cases (Helpdesk), Documentation (/docs), Assets, Network, Servers, Monitoring, Maintenance, Production, Security, Backups, Reports, Branding (admin), Admin.',
            '',
            'In-app documentation guides (Documentation module — these ARE the product docs; never say there are no docs):',
            ...DocumentationCatalog::collection()->map(fn (array $doc) => sprintf(
                '- %s · %s · audience: %s · /docs/%s',
                $doc['title'],
                $doc['summary'],
                $doc['audience'] ?? 'All users',
                $doc['slug']
            ))->all(),
        ];

        $lookups = $this->lookupMentions($message, $assets, $devices, $tickets, $people, $open);
        $docHits = $this->lookupDocumentation($message);
        if ($docHits !== []) {
            array_push($lookups, ...$docHits);
        }
        if ($lookups !== []) {
            array_push($lines, '', 'LOOKUP HITS for the current question:', ...$lookups);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function lookupDocumentation(string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            return [];
        }

        $needle = Str::lower($message);
        $asksAboutDocs = str_contains($needle, 'doc')
            || str_contains($needle, 'guide')
            || str_contains($needle, 'how to')
            || str_contains($needle, 'help')
            || str_contains($needle, 'manual')
            || str_contains($needle, 'tutorial')
            || str_contains($needle, 'getting started')
            || str_contains($needle, 'glossary');

        $matches = DocumentationCatalog::search($message, 8);
        if ($matches->isEmpty() && $asksAboutDocs) {
            $matches = DocumentationCatalog::collection()->take(8);
        }

        if ($matches->isEmpty()) {
            return [];
        }

        $lines = [
            '- Documentation is available in the Documentation module (left nav / top tabs → Documentation, path /docs).',
        ];

        foreach ($matches as $doc) {
            $lines[] = sprintf(
                '- Guide: %s — %s (/docs/%s)',
                $doc['title'],
                $doc['summary'],
                $doc['slug']
            );
        }

        return $lines;
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  Collection<int, NetworkDevice>  $devices
     * @param  Collection<int, SupportTicket>  $tickets
     * @param  Collection<int, User>  $people
     * @param  Collection<int, SupportTicket>  $openTickets
     * @return list<string>
     */
    private function lookupMentions(
        string $message,
        Collection $assets,
        Collection $devices,
        Collection $tickets,
        Collection $people,
        Collection $openTickets,
    ): array {
        $message = trim($message);
        if ($message === '') {
            return [];
        }

        preg_match_all('/\bPG-[A-Z]+-\d+\b/i', $message, $tagMatches);
        preg_match_all('/\bPLT-[A-Z0-9-]+\b/i', $message, $hostMatches);
        preg_match_all('/\bPG-\d{4}-\d+\b/i', $message, $ticketMatches);

        $tags = collect($tagMatches[0] ?? [])->map(fn ($value) => strtoupper($value))->unique()->values();
        $hosts = collect($hostMatches[0] ?? [])->map(fn ($value) => strtoupper($value))->unique()->values();
        $ticketNumbers = collect($ticketMatches[0] ?? [])->map(fn ($value) => strtoupper($value))->unique()->values();

        $lines = [];

        foreach ($this->matchPeople($message, $people) as $person) {
            $assigned = $openTickets
                ->where('assigned_to', $person->id)
                ->values()
                ->map(fn (SupportTicket $ticket) => $ticket->ticket_number.' ('.$ticket->subject.')')
                ->all();

            $lines[] = sprintf(
                '- Person contact %s: email %s · role %s · department %s · account status %s · phone not stored in demo · open assigned cases: %s',
                $person->name,
                $person->email,
                $person->roleLabel(),
                $person->department?->name ?? 'n/a',
                Labels::status((string) $person->status),
                $assigned === [] ? 'none' : implode('; ', $assigned)
            );
        }

        foreach ($tags as $tag) {
            $asset = $assets->first(fn (Asset $item) => strtoupper($item->asset_tag) === $tag);
            $device = $devices->first(fn (NetworkDevice $item) => strtoupper((string) $item->asset?->asset_tag) === $tag);

            if (! $asset && ! $device) {
                $lines[] = "- {$tag}: not found in asset or network-device register";
                continue;
            }

            if ($asset) {
                $lines[] = sprintf(
                    '- Asset %s: %s · status %s · condition %s · category %s · location %s · dept %s · %s %s',
                    $asset->asset_tag,
                    $asset->name,
                    Labels::status((string) $asset->status),
                    Labels::status((string) ($asset->condition ?: 'n/a')),
                    $asset->category?->name ?? 'n/a',
                    $asset->location?->name ?? 'n/a',
                    $asset->department?->name ?? 'n/a',
                    $asset->manufacturer ?: '',
                    $asset->model ?: ''
                );
            }

            if ($device) {
                $lines[] = sprintf(
                    '- Network device for %s: hostname %s · type %s · IP %s · status %s · location %s',
                    $tag,
                    $device->hostname,
                    Labels::status((string) $device->device_type),
                    $device->management_ip ?: 'n/a',
                    Labels::status((string) $device->status),
                    $device->location?->name ?? 'n/a'
                );
            }
        }

        foreach ($hosts as $host) {
            $device = $devices->first(fn (NetworkDevice $item) => strtoupper($item->hostname) === $host);
            if (! $device) {
                $lines[] = "- Hostname {$host}: not found in network devices";
                continue;
            }
            $lines[] = sprintf(
                '- Hostname %s: asset %s · type %s · IP %s · status %s · location %s',
                $device->hostname,
                $device->asset?->asset_tag ?? 'n/a',
                Labels::status((string) $device->device_type),
                $device->management_ip ?: 'n/a',
                Labels::status((string) $device->status),
                $device->location?->name ?? 'n/a'
            );
        }

        foreach ($ticketNumbers as $number) {
            $ticket = $tickets->first(fn (SupportTicket $item) => strtoupper($item->ticket_number) === $number);
            if (! $ticket) {
                $lines[] = "- Case {$number}: not visible in this user's case list";
                continue;
            }
            $lines[] = sprintf(
                '- Case %s: %s · %s · %s · assignee %s',
                $ticket->ticket_number,
                $ticket->subject,
                Labels::status((string) $ticket->status),
                Labels::status((string) $ticket->priority),
                $ticket->assignee?->name ?? 'unassigned'
            );
        }

        return $lines;
    }

    /**
     * @param  Collection<int, User>  $people
     * @return Collection<int, User>
     */
    private function matchPeople(string $message, Collection $people): Collection
    {
        $haystack = mb_strtolower($message);
        $matched = collect();

        foreach ($people as $person) {
            $full = mb_strtolower($person->name);
            $parts = preg_split('/\s+/', $full) ?: [];
            $emailLocal = mb_strtolower(strtok($person->email, '@') ?: '');

            $hit = str_contains($haystack, $full)
                || ($emailLocal !== '' && str_contains($haystack, $emailLocal))
                || collect($parts)->contains(fn (string $part) => mb_strlen($part) >= 3 && preg_match('/\b'.preg_quote($part, '/').'\b/u', $haystack));

            if ($hit) {
                $matched->push($person);
            }
        }

        return $matched->unique('id')->values();
    }

    private function personLine(User $person): string
    {
        return sprintf(
            '- %s · %s · %s · %s · %s · %s · %s · %s',
            $person->name,
            $person->job_title ?: 'n/a',
            $person->email,
            $person->contactLine(),
            $person->fullAddress(),
            $person->roleLabel(),
            $person->department?->name ?? 'n/a',
            Labels::status((string) $person->status)
        );
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function formatCounts(array $counts): string
    {
        if ($counts === []) {
            return 'none';
        }

        ksort($counts);

        return collect($counts)
            ->map(fn ($count, $status) => Labels::status((string) $status).'='.$count)
            ->implode(', ');
    }

    /**
     * @param  Collection<int, SupportTicket>  $tickets
     * @return list<string>
     */
    private function ticketLines(Collection $tickets): array
    {
        if ($tickets->isEmpty()) {
            return ['- none'];
        }

        return $tickets->map(function (SupportTicket $ticket) {
            return sprintf(
                '- %s · %s · %s · %s · assignee %s · dept %s · due %s',
                $ticket->ticket_number,
                $ticket->subject,
                Labels::status((string) $ticket->status),
                Labels::status((string) $ticket->priority),
                $ticket->assignee?->name ?? 'unassigned',
                $ticket->department?->name ?? 'n/a',
                optional($ticket->due_at)?->toDateString() ?? 'none'
            );
        })->all();
    }
}
