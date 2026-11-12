<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'trigger' => ['nullable', 'in:@,#,all'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        $trigger = $data['trigger'] ?? 'all';
        $like = '%'.$term.'%';
        $user = $request->user();
        $items = [];

        if (in_array($trigger, ['@', 'all'], true)) {
            $people = User::query()
                ->with(['roles:id,name', 'department:id,code'])
                ->where('status', 'active')
                ->when($term !== '', function ($query) use ($like, $term) {
                    $query->where(function ($inner) use ($like, $term) {
                        $inner->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('job_title', 'like', $like)
                            ->orWhereRaw("LOWER(SUBSTRING_INDEX(email, '@', 1)) like ?", ['%'.strtolower($term).'%']);
                    });
                })
                ->orderBy('name')
                ->limit($trigger === '@' ? 80 : 40)
                ->get(['id', 'name', 'email', 'job_title', 'department_id']);

            foreach ($people as $person) {
                $handle = strtok((string) $person->email, '@') ?: $person->email;
                $meta = collect([
                    $person->job_title,
                    $person->roleLabel(),
                    $person->department?->code,
                    $person->email,
                ])->filter()->implode(' · ');

                $items[] = [
                    'type' => 'people',
                    'label' => $person->name,
                    'meta' => $meta,
                    'insert' => '@'.$handle,
                    'url' => route('people.show', $person),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('assets.view')) {
            $assetLimit = $trigger === '#' ? 100 : 20;
            $assets = Asset::query()
                ->visibleTo($user)
                ->when($term !== '', function ($query) use ($like, $term) {
                    $query->where(function ($inner) use ($like, $term) {
                        $inner->where('asset_tag', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('serial_number', 'like', $like);

                        // Allow typing without the PG- prefix (e.g. #LBL or #0501).
                        $digits = preg_replace('/\D+/', '', $term);
                        if ($digits !== '') {
                            $inner->orWhere('asset_tag', 'like', '%'.$digits.'%');
                        }
                    });
                })
                ->orderBy('asset_tag')
                ->limit($assetLimit)
                ->get(['id', 'asset_tag', 'name']);

            foreach ($assets as $asset) {
                $items[] = [
                    'type' => 'asset',
                    'label' => $asset->asset_tag,
                    'meta' => (string) ($asset->name ?: ''),
                    'insert' => $asset->asset_tag,
                    'url' => route('assets.show', $asset),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $caseLimit = $trigger === '#' ? 40 : 8;
            $tickets = SupportTicket::query()
                ->visibleTo($user)
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('ticket_number', 'like', $like)
                            ->orWhere('subject', 'like', $like);
                    });
                })
                ->latest('reported_at')
                ->limit($caseLimit)
                ->get(['id', 'ticket_number', 'subject']);

            foreach ($tickets as $ticket) {
                $items[] = [
                    'type' => 'case',
                    'label' => $ticket->ticket_number,
                    'meta' => (string) ($ticket->subject ?: ''),
                    'insert' => $ticket->ticket_number,
                    'url' => route('helpdesk.show', $ticket),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('network.view')) {
            $deviceLimit = $trigger === '#' ? 40 : 8;
            $devices = NetworkDevice::query()
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('hostname', 'like', $like)
                            ->orWhere('management_ip', 'like', $like);
                    });
                })
                ->orderBy('hostname')
                ->limit($deviceLimit)
                ->get(['id', 'hostname']);

            foreach ($devices as $device) {
                $items[] = [
                    'type' => 'device',
                    'label' => $device->hostname,
                    'meta' => '',
                    'insert' => $device->hostname,
                    'url' => route('network.show', $device),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('servers.view')) {
            $serverLimit = $trigger === '#' ? 40 : 8;
            $servers = Server::query()
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('hostname', 'like', $like)
                            ->orWhere('server_role', 'like', $like);
                    });
                })
                ->orderBy('hostname')
                ->limit($serverLimit)
                ->get(['id', 'hostname']);

            foreach ($servers as $server) {
                $items[] = [
                    'type' => 'server',
                    'label' => $server->hostname,
                    'meta' => '',
                    'insert' => $server->hostname,
                    'url' => route('servers.show', $server),
                ];
            }
        }

        // Keep @people results intact; # lookups can return a full asset/case list.
        $limit = match ($trigger) {
            '@' => 80,
            '#' => 120,
            default => 40,
        };

        return response()->json([
            'ok' => true,
            'items' => array_slice($items, 0, $limit),
        ]);
    }
}
