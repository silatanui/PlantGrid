<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Location;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        $user = $request->user();
        $items = [];

        if (in_array($trigger, ['@', 'all'], true)) {
            $needle = mb_strtolower($term);
            if ($needle === '' || str_contains('everyone', $needle) || str_contains($needle, 'every')) {
                $items[] = [
                    'type' => 'everyone',
                    'label' => 'everyone',
                    'meta' => 'Notify everyone on this case (requester, owner, partners)',
                    'insert' => '@everyone',
                    'token' => '@everyone',
                    'url' => null,
                ];
            }

            $departments = Department::query()
                ->where('status', 'active')
                ->when($term !== '', function (Builder $query) use ($term) {
                    $needle = mb_strtolower($term);
                    $query->where(function (Builder $group) use ($term, $needle) {
                        $this->applyTextSearch($group, $term, ['name', 'code']);
                        if (str_contains('it_department', $needle) || str_contains('ict', $needle) || $needle === 'it') {
                            $group->orWhere('code', 'ICT');
                        }
                    });
                })
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'code']);

            foreach ($departments as $department) {
                $code = strtolower((string) $department->code);
                $alias = $code === 'ict' ? 'it_department' : $code.'_department';
                $items[] = [
                    'type' => 'department',
                    'label' => $department->code,
                    'meta' => 'Department · '.$department->name.' · @'.$alias,
                    'insert' => '@'.$alias,
                    'token' => '@['.$department->name.'](department:'.$department->id.')',
                    'url' => null,
                ];
            }

            $people = User::query()
                ->with(['roles:id,name', 'department:id,code'])
                ->where('status', 'active')
                ->when($term !== '', function (Builder $query) use ($term) {
                    $words = $this->searchWords($term);
                    foreach ($words as $word) {
                        $like = '%'.$this->escapeLike($word).'%';
                        $handleLike = '%'.mb_strtolower($word).'%';
                        $query->where(function (Builder $inner) use ($like, $handleLike) {
                            $inner->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('job_title', 'like', $like)
                                ->orWhereRaw("LOWER(SUBSTRING_INDEX(email, '@', 1)) like ?", [$handleLike]);
                        });
                    }
                })
                ->orderBy('name')
                ->limit($trigger === '@' ? 80 : 40)
                ->get(['id', 'name', 'email', 'job_title', 'department_id']);

            $people = $this->rankByPrefix($people, $term, fn (User $person) => [
                $person->name,
                strtok((string) $person->email, '@') ?: (string) $person->email,
            ]);

            foreach ($people as $person) {
                $meta = collect([
                    'Person',
                    $person->job_title,
                    $person->roleLabel(),
                    $person->department?->code,
                    $person->email,
                ])->filter()->implode(' · ');

                $items[] = [
                    'type' => 'people',
                    'label' => $person->name,
                    'meta' => $meta,
                    'insert' => '@['.$person->name.'](people:'.$person->id.')',
                    'token' => '@['.$person->name.'](people:'.$person->id.')',
                    'url' => route('people.show', $person),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('assets.view')) {
            $assetLimit = $trigger === '#' ? 100 : 20;
            $assets = Asset::query()
                ->visibleTo($user)
                ->when($term !== '', function (Builder $query) use ($term) {
                    $digits = preg_replace('/\D+/', '', $term) ?: '';
                    $query->where(function (Builder $group) use ($term, $digits) {
                        $this->applyTextSearch($group, $term, ['asset_tag', 'name', 'serial_number']);
                        if ($digits !== '') {
                            $group->orWhere('asset_tag', 'like', '%'.$this->escapeLike($digits).'%');
                        }
                    });
                })
                ->orderBy('asset_tag')
                ->limit($assetLimit)
                ->get(['id', 'asset_tag', 'name']);

            $assets = $this->rankByPrefix($assets, $term, fn (Asset $asset) => [
                $asset->asset_tag,
                $asset->name,
            ]);

            foreach ($assets as $asset) {
                $items[] = [
                    'type' => 'asset',
                    'label' => $asset->asset_tag,
                    'meta' => trim('Asset · '.((string) ($asset->name ?: ''))),
                    'insert' => '@['.$asset->asset_tag.'](asset:'.$asset->id.')',
                    'token' => '@['.$asset->asset_tag.'](asset:'.$asset->id.')',
                    'url' => route('assets.show', $asset),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasAnyPermission(['tickets.view.own', 'tickets.view.department', 'tickets.view.all'])) {
            $caseLimit = $trigger === '#' ? 40 : 8;
            $tickets = SupportTicket::query()
                ->visibleTo($user)
                ->when($term !== '', function (Builder $query) use ($term) {
                    $this->applyTextSearch($query, $term, ['ticket_number', 'subject']);
                })
                ->latest('reported_at')
                ->limit($caseLimit)
                ->get(['id', 'ticket_number', 'subject']);

            $tickets = $this->rankByPrefix($tickets, $term, fn (SupportTicket $ticket) => [
                $ticket->ticket_number,
                $ticket->subject,
            ]);

            foreach ($tickets as $ticket) {
                $items[] = [
                    'type' => 'case',
                    'label' => $ticket->ticket_number,
                    'meta' => trim('Case · '.((string) ($ticket->subject ?: ''))),
                    'insert' => '@['.$ticket->ticket_number.'](case:'.$ticket->id.')',
                    'token' => '@['.$ticket->ticket_number.'](case:'.$ticket->id.')',
                    'url' => route('helpdesk.show', $ticket),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('network.view')) {
            $deviceLimit = $trigger === '#' ? 40 : 8;
            $devices = NetworkDevice::query()
                ->when($term !== '', function (Builder $query) use ($term) {
                    $this->applyTextSearch($query, $term, ['hostname', 'management_ip']);
                })
                ->orderBy('hostname')
                ->limit($deviceLimit)
                ->get(['id', 'hostname']);

            $devices = $this->rankByPrefix($devices, $term, fn (NetworkDevice $device) => [$device->hostname]);

            foreach ($devices as $device) {
                $items[] = [
                    'type' => 'device',
                    'label' => $device->hostname,
                    'meta' => 'Device',
                    'insert' => '@['.$device->hostname.'](device:'.$device->id.')',
                    'token' => '@['.$device->hostname.'](device:'.$device->id.')',
                    'url' => route('network.show', $device),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasPermission('servers.view')) {
            $serverLimit = $trigger === '#' ? 40 : 8;
            $servers = Server::query()
                ->when($term !== '', function (Builder $query) use ($term) {
                    $this->applyTextSearch($query, $term, ['hostname', 'server_role']);
                })
                ->orderBy('hostname')
                ->limit($serverLimit)
                ->get(['id', 'hostname']);

            $servers = $this->rankByPrefix($servers, $term, fn (Server $server) => [$server->hostname]);

            foreach ($servers as $server) {
                $items[] = [
                    'type' => 'server',
                    'label' => $server->hostname,
                    'meta' => 'Server',
                    'insert' => '@['.$server->hostname.'](server:'.$server->id.')',
                    'token' => '@['.$server->hostname.'](server:'.$server->id.')',
                    'url' => route('servers.show', $server),
                ];
            }
        }

        if (in_array($trigger, ['#', 'all'], true) && $user?->hasAnyPermission(['assets.view', 'network.view'])) {
            $locationLimit = $trigger === '#' ? 40 : 10;
            $locations = Location::query()
                ->when($term !== '', function (Builder $query) use ($term) {
                    $this->applyTextSearch($query, $term, ['code', 'name', 'description']);
                })
                ->orderBy('code')
                ->limit($locationLimit)
                ->get(['id', 'code', 'name']);

            $locations = $this->rankByPrefix($locations, $term, fn (Location $location) => [
                $location->code,
                $location->name,
            ]);

            foreach ($locations as $location) {
                $items[] = [
                    'type' => 'location',
                    'label' => $location->code,
                    'meta' => 'Location · '.(string) $location->name,
                    'insert' => $location->code,
                    'token' => '@['.$location->name.'](location:'.$location->id.')',
                    'url' => route('locations.show', $location),
                ];
            }
        }

        // When filtering, keep people near the top for @ and match score for #.
        if ($term !== '') {
            $items = $this->rankItems($items, $term);
        }

        $limit = match ($trigger) {
            '@' => 80,
            '#' => 120,
            default => 40,
        };

        return response()->json([
            'ok' => true,
            'items' => array_values(array_slice($items, 0, $limit)),
        ]);
    }

    /**
     * @param  list<string>  $columns
     */
    private function applyTextSearch(Builder $query, string $term, array $columns): void
    {
        $words = $this->searchWords($term);
        foreach ($words as $word) {
            $like = '%'.$this->escapeLike($word).'%';
            $query->where(function (Builder $outer) use ($columns, $like) {
                foreach ($columns as $index => $column) {
                    if ($index === 0) {
                        $outer->where($column, 'like', $like);
                    } else {
                        $outer->orWhere($column, 'like', $like);
                    }
                }
            });
        }
    }

    /**
     * @return list<string>
     */
    private function searchWords(string $term): array
    {
        $parts = preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map(
            fn ($part) => mb_substr((string) $part, 0, 40),
            $parts
        )));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @template T of object
     *
     * @param  \Illuminate\Support\Collection<int, T>|\Illuminate\Database\Eloquent\Collection<int, T>  $rows
     * @param  callable(T): list<string|null>  $fields
     * @return \Illuminate\Support\Collection<int, T>
     */
    private function rankByPrefix($rows, string $term, callable $fields)
    {
        $needle = mb_strtolower(trim($term));
        if ($needle === '') {
            return $rows->values();
        }

        return $rows->sortBy(function ($row) use ($fields, $needle) {
            $best = 50;
            foreach ($fields($row) as $field) {
                $value = mb_strtolower(trim((string) $field));
                if ($value === '') {
                    continue;
                }
                if (str_starts_with($value, $needle)) {
                    $best = min($best, 0);
                } elseif (str_contains($value, $needle)) {
                    $best = min($best, 10 + mb_strpos($value, $needle));
                }
            }

            return $best;
        })->values();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function rankItems(array $items, string $term): array
    {
        $needle = mb_strtolower(trim($term));
        $words = $this->searchWords($term);

        usort($items, function (array $a, array $b) use ($needle, $words) {
            return $this->itemScore($a, $needle, $words) <=> $this->itemScore($b, $needle, $words);
        });

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $words
     */
    private function itemScore(array $item, string $needle, array $words): int
    {
        $label = mb_strtolower((string) ($item['label'] ?? ''));
        $meta = mb_strtolower((string) ($item['meta'] ?? ''));
        $hay = trim($label.' '.$meta);
        $typeBoost = match ($item['type'] ?? '') {
            'people' => 0,
            'case', 'asset' => 1,
            default => 2,
        };

        if ($needle !== '' && str_starts_with($label, $needle)) {
            return $typeBoost;
        }

        foreach ($words as $word) {
            $part = mb_strtolower($word);
            if ($part !== '' && ! str_contains($hay, $part)) {
                return 100 + $typeBoost;
            }
        }

        if ($needle !== '' && str_contains($label, $needle)) {
            return 10 + $typeBoost;
        }

        if ($needle !== '' && str_contains($hay, $needle)) {
            return 20 + $typeBoost;
        }

        return 40 + $typeBoost;
    }
}
