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
                ->with(['roles:id,name', 'department:id,name'])
                ->where('status', 'active')
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                })
                ->orderBy('name')
                ->limit(8)
                ->get();

            foreach ($people as $person) {
                $handle = strtok($person->email, '@') ?: $person->email;
                $items[] = [
                    'type' => 'people',
                    'label' => $person->name,
                    'meta' => $person->roleLabel().' · '.$person->email,
                    'token' => '@['.$person->name.'](people:'.$person->id.')',
                    'insert' => '@'.$handle,
                    'url' => route('people.show', $person),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $tickets = SupportTicket::query()
                ->visibleTo($user)
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('ticket_number', 'like', $like)
                            ->orWhere('subject', 'like', $like);
                    });
                })
                ->latest('reported_at')
                ->limit(6)
                ->get(['id', 'ticket_number', 'subject', 'status']);

            foreach ($tickets as $ticket) {
                $items[] = [
                    'type' => 'case',
                    'label' => $ticket->ticket_number,
                    'meta' => $ticket->subject,
                    'token' => '@['.$ticket->ticket_number.'](case:'.$ticket->id.')',
                    'insert' => $ticket->ticket_number,
                    'url' => route('helpdesk.show', $ticket),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('assets.view')) {
            $assets = Asset::query()
                ->visibleTo($user)
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('asset_tag', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
                })
                ->orderBy('asset_tag')
                ->limit(6)
                ->get(['id', 'asset_tag', 'name', 'status']);

            foreach ($assets as $asset) {
                $items[] = [
                    'type' => 'asset',
                    'label' => $asset->asset_tag,
                    'meta' => $asset->name,
                    'token' => '@['.$asset->asset_tag.'](asset:'.$asset->id.')',
                    'insert' => $asset->asset_tag,
                    'url' => route('assets.show', $asset),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('network.view')) {
            $devices = NetworkDevice::query()
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('hostname', 'like', $like)
                            ->orWhere('management_ip', 'like', $like);
                    });
                })
                ->orderBy('hostname')
                ->limit(5)
                ->get(['id', 'hostname', 'device_type', 'management_ip']);

            foreach ($devices as $device) {
                $items[] = [
                    'type' => 'device',
                    'label' => $device->hostname,
                    'meta' => trim(($device->device_type ?: 'device').($device->management_ip ? ' · '.$device->management_ip : '')),
                    'token' => '@['.$device->hostname.'](device:'.$device->id.')',
                    'insert' => $device->hostname,
                    'url' => route('network.show', $device),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('servers.view')) {
            $servers = Server::query()
                ->when($term !== '', function ($query) use ($like) {
                    $query->where(function ($inner) use ($like) {
                        $inner->where('hostname', 'like', $like)
                            ->orWhere('server_role', 'like', $like);
                    });
                })
                ->orderBy('hostname')
                ->limit(5)
                ->get(['id', 'hostname', 'server_role', 'status']);

            foreach ($servers as $server) {
                $items[] = [
                    'type' => 'server',
                    'label' => $server->hostname,
                    'meta' => $server->server_role ?: 'Server',
                    'token' => '@['.$server->hostname.'](server:'.$server->id.')',
                    'insert' => $server->hostname,
                    'url' => route('servers.show', $server),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'items' => array_slice($items, 0, 18),
        ]);
    }
}
