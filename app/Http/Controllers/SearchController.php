<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\DocumentationCatalog;
use App\Support\Labels;
use App\Support\SearchPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim($request->string('q')->toString());
        $results = $this->search($request->user(), $term, 12);

        return view('search.index', [
            'title' => 'Search',
            'term' => $term,
            'pages' => $results['pages'],
            'people' => $results['people'],
            'assets' => $results['assets'],
            'tickets' => $results['tickets'],
            'servers' => $results['servers'],
            'devices' => $results['devices'],
            'docs' => $results['docs'],
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        if (mb_strlen($term) < 1) {
            return response()->json([
                'ok' => true,
                'term' => '',
                'groups' => [],
                'total' => 0,
                'search_url' => route('search'),
            ]);
        }

        $results = $this->search($request->user(), $term, 6);
        $groups = [];

        if ($results['pages']->isNotEmpty()) {
            $groups[] = [
                'type' => 'pages',
                'label' => 'Pages',
                'items' => $results['pages']->map(fn (array $page) => [
                    'id' => $page['url'],
                    'title' => $page['title'],
                    'subtitle' => $page['summary'],
                    'meta' => 'Open',
                    'url' => $page['url'],
                ])->values(),
            ];
        }

        if ($results['tickets']->isNotEmpty()) {
            $intent = SearchPages::caseListIntent($term);
            $groups[] = [
                'type' => 'cases',
                'label' => $intent['label'] ?? 'Cases',
                'items' => $results['tickets']->map(fn (SupportTicket $ticket) => [
                    'id' => $ticket->id,
                    'title' => $ticket->ticket_number,
                    'subtitle' => $ticket->subject,
                    'meta' => Labels::status((string) $ticket->status),
                    'url' => route('helpdesk.show', $ticket),
                ])->values(),
            ];
        }

        if ($results['people']->isNotEmpty()) {
            $groups[] = [
                'type' => 'people',
                'label' => 'People',
                'items' => $results['people']->map(fn (User $person) => [
                    'id' => $person->id,
                    'title' => $person->name,
                    'subtitle' => ($person->job_title ?: $person->roleLabel()).' · '.$person->email,
                    'meta' => $person->mobile ?: ($person->phone ?: ($person->department?->name ?: 'Contact')),
                    'url' => route('people.show', $person),
                ])->values(),
            ];
        }

        if ($results['assets']->isNotEmpty()) {
            $groups[] = [
                'type' => 'assets',
                'label' => 'Assets',
                'items' => $results['assets']->map(fn (Asset $asset) => [
                    'id' => $asset->id,
                    'title' => $asset->asset_tag,
                    'subtitle' => $asset->name,
                    'meta' => Labels::status((string) $asset->status),
                    'url' => route('assets.show', $asset),
                ])->values(),
            ];
        }

        if ($results['servers']->isNotEmpty()) {
            $groups[] = [
                'type' => 'servers',
                'label' => 'Servers',
                'items' => $results['servers']->map(fn (Server $server) => [
                    'id' => $server->id,
                    'title' => $server->hostname,
                    'subtitle' => $server->server_role ?: 'Server',
                    'meta' => Labels::status((string) $server->status),
                    'url' => route('servers.show', $server),
                ])->values(),
            ];
        }

        if ($results['devices']->isNotEmpty()) {
            $groups[] = [
                'type' => 'network',
                'label' => 'Network devices',
                'items' => $results['devices']->map(fn (NetworkDevice $device) => [
                    'id' => $device->id,
                    'title' => $device->hostname,
                    'subtitle' => Labels::status((string) $device->device_type).($device->management_ip ? ' · '.$device->management_ip : ''),
                    'meta' => Labels::status((string) $device->status),
                    'url' => route('network.show', $device),
                ])->values(),
            ];
        }

        if ($results['docs']->isNotEmpty()) {
            $groups[] = [
                'type' => 'docs',
                'label' => 'Documentation',
                'items' => $results['docs']->map(fn (array $doc) => [
                    'id' => $doc['slug'],
                    'title' => $doc['title'],
                    'subtitle' => $doc['summary'],
                    'meta' => 'Guide',
                    'url' => route('docs.show', $doc['slug']),
                ])->values(),
            ];
        }

        $total = collect($groups)->sum(fn (array $group) => count($group['items']));

        return response()->json([
            'ok' => true,
            'term' => $term,
            'groups' => $groups,
            'total' => $total,
            'search_url' => route('search', ['q' => $term]),
        ]);
    }

    /**
     * @return array{
     *     pages: Collection<int, array{title: string, summary: string, url: string}>,
     *     people: Collection<int, User>,
     *     assets: Collection<int, Asset>,
     *     tickets: Collection<int, SupportTicket>,
     *     servers: Collection<int, Server>,
     *     devices: Collection<int, NetworkDevice>,
     *     docs: Collection<int, array<string, mixed>>
     * }
     */
    private function search(?User $user, string $term, int $limit = 8): array
    {
        if (! $user || $term === '') {
            return [
                'pages' => collect(),
                'people' => collect(),
                'assets' => collect(),
                'tickets' => collect(),
                'servers' => collect(),
                'devices' => collect(),
                'docs' => collect(),
            ];
        }

        $like = '%'.$term.'%';
        $caseIntent = SearchPages::caseListIntent($term);
        $pages = SearchPages::search($user, $term, $limit);

        $people = $caseIntent
            ? collect()
            : User::query()
                ->with(['roles:id,name', 'department:id,name'])
                ->where('status', 'active')
                ->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('job_title', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('extension', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'like', $like))
                        ->orWhereHas('department', fn ($deptQuery) => $deptQuery->where('name', 'like', $like));
                })
                ->orderBy('name')
                ->limit($limit)
                ->get();

        $assets = (! $caseIntent && $user->hasPermission('assets.view'))
            ? Asset::query()->visibleTo($user)->where(function ($query) use ($like) {
                $query->where('asset_tag', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('serial_number', 'like', $like);
            })->orderBy('asset_tag')->limit($limit)->get()
            : collect();

        $tickets = collect();
        if ($user->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            if ($caseIntent) {
                $ticketsQuery = SupportTicket::query()->visibleTo($user);
                if (($caseIntent['scope'] ?? 'mine') === 'assigned') {
                    $ticketsQuery->where('assigned_to', $user->id);
                } else {
                    $ticketsQuery->where('requester_id', $user->id);
                }
                SearchPages::applyStatusFilter($ticketsQuery, $caseIntent['status'] ?? null);
                $tickets = $ticketsQuery->latest('reported_at')->limit($limit)->get();
            } else {
                $tickets = SupportTicket::query()->visibleTo($user)->where(function ($query) use ($like) {
                    $query->where('ticket_number', 'like', $like)
                        ->orWhere('subject', 'like', $like);
                })->latest('reported_at')->limit($limit)->get();
            }
        }

        $servers = (! $caseIntent && $user->hasPermission('servers.view'))
            ? Server::query()->where(function ($query) use ($like) {
                $query->where('hostname', 'like', $like)
                    ->orWhere('server_role', 'like', $like)
                    ->orWhere('operating_system', 'like', $like);
            })->orderBy('hostname')->limit($limit)->get()
            : collect();

        $devices = (! $caseIntent && $user->hasPermission('network.view'))
            ? NetworkDevice::query()->where(function ($query) use ($like) {
                $query->where('hostname', 'like', $like)
                    ->orWhere('management_ip', 'like', $like)
                    ->orWhere('device_type', 'like', $like)
                    ->orWhereHas('asset', function ($assetQuery) use ($like) {
                        $assetQuery->where('asset_tag', 'like', $like)->orWhere('name', 'like', $like);
                    });
            })->with('asset:id,asset_tag,name')->orderBy('hostname')->limit($limit)->get()
            : collect();

        $docs = (! $caseIntent && $user->hasPermission('dashboard.view'))
            ? DocumentationCatalog::search($term, $limit)
            : collect();

        return [
            'pages' => $pages,
            'people' => $people,
            'assets' => $assets,
            'tickets' => $tickets,
            'servers' => $servers,
            'devices' => $devices,
            'docs' => $docs,
        ];
    }
}
