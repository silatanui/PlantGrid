<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Location;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class AiEntityIndex
{
    /**
     * Compact lookup maps for client-side chat linkification.
     *
     * @return array{entities: array<string, mixed>, urls: array<string, string>}
     */
    public static function forUser(?User $user = null): array
    {
        $user ??= auth()->user();

        $cases = [];
        if ($user) {
            foreach (
                SupportTicket::query()
                    ->visibleTo($user)
                    ->get(['id', 'ticket_number']) as $ticket
            ) {
                $cases[strtoupper((string) $ticket->ticket_number)] = $ticket->id;
            }
        }

        $assets = [];
        if ($user?->hasPermission('assets.view')) {
            foreach (Asset::query()->visibleTo($user)->get(['id', 'asset_tag']) as $asset) {
                $assets[strtoupper((string) $asset->asset_tag)] = $asset->id;
            }
        }

        $devices = [];
        if ($user?->hasPermission('network.view')) {
            foreach (NetworkDevice::query()->get(['id', 'hostname']) as $device) {
                $devices[strtoupper((string) $device->hostname)] = $device->id;
            }
        }

        $servers = [];
        if ($user?->hasPermission('servers.view')) {
            foreach (Server::query()->get(['id', 'hostname']) as $server) {
                $servers[strtoupper((string) $server->hostname)] = $server->id;
            }
        }

        $people = [];
        $peopleLabels = [];
        $peopleByName = [];
        foreach (User::query()->where('status', 'active')->get(['id', 'name', 'email']) as $person) {
            $handle = mb_strtolower((string) strtok((string) $person->email, '@'));
            if ($handle === '') {
                continue;
            }
            $people[$handle] = $person->id;
            $peopleLabels[$handle] = $person->name;

            // Full display names so chat replies can link "Faith Cheruiyot" without an @handle.
            $nameKey = mb_strtolower(trim((string) $person->name));
            if ($nameKey !== '' && str_contains($nameKey, ' ')) {
                $peopleByName[$nameKey] = [
                    'id' => $person->id,
                    'label' => $person->name,
                    'handle' => $handle,
                ];
            }
        }

        $departments = [];
        foreach (Department::query()->where('status', 'active')->get(['id', 'name', 'code']) as $department) {
            $code = mb_strtolower((string) $department->code);
            $slug = mb_strtolower(str_replace(' ', '_', (string) $department->name));
            $aliases = array_unique(array_filter([
                $code,
                $slug,
                $code.'_department',
                $slug.'_department',
            ]));
            if ($code === 'ict') {
                $aliases = array_merge($aliases, ['it', 'it_department', 'ict_department']);
            }
            foreach ($aliases as $alias) {
                $departments[$alias] = [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                ];
            }
        }

        $locations = [];
        foreach (Location::query()->get(['id', 'code', 'name']) as $location) {
            $locations[strtoupper((string) $location->code)] = [
                'id' => $location->id,
                'name' => $location->name,
            ];
        }

        return [
            'entities' => [
                'cases' => $cases,
                'assets' => $assets,
                'devices' => $devices,
                'servers' => $servers,
                'people' => $people,
                'people_labels' => $peopleLabels,
                'people_by_name' => $peopleByName,
                'departments' => $departments,
                'locations' => $locations,
            ],
            'urls' => [
                'people' => self::template('people.show', 'person'),
                'case' => self::template('helpdesk.show', 'ticket'),
                'asset' => self::template('assets.show', 'asset'),
                'device' => self::template('network.show', 'device'),
                'server' => self::template('servers.show', 'server'),
                'location' => self::template('locations.show', 'location'),
                'docs' => self::docsTemplate(),
            ],
        ];
    }

    private static function template(string $routeName, string $param): string
    {
        if (! Route::has($routeName)) {
            return '';
        }

        // Build a concrete URL then swap the id for a client placeholder.
        $url = route($routeName, [$param => 999999001]);

        return str_replace(['999999001', '%7B%7B', '%7D%7D'], ['__ID__', '{{', '}}'], $url);
    }

    private static function docsTemplate(): string
    {
        if (! Route::has('docs.show')) {
            return '';
        }

        return str_replace('__SLUG_PLACEHOLDER__', '__SLUG__', route('docs.show', ['slug' => '__SLUG_PLACEHOLDER__']));
    }
}
