<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Location;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RichText
{
    /**
     * Active users explicitly addressed in note text (@handle, @everyone, @department, or structured tokens).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function mentionedUsers(?string $text, ?SupportTicket $ticket = null)
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return collect();
        }

        $ids = [];

        if (preg_match_all('/@\[(.*?)\]\((?:people|user):(\d+)\)/u', $text, $structured)) {
            foreach ($structured[2] as $id) {
                $ids[] = (int) $id;
            }
        }

        if (preg_match_all('/@\[(.*?)\]\(department:(\d+)\)/u', $text, $departments)) {
            foreach ($departments[2] as $departmentId) {
                foreach (self::userIdsForDepartment((int) $departmentId) as $id) {
                    $ids[] = $id;
                }
            }
        }

        $wantsEveryone = (bool) preg_match('/(^|[\s(>])@everyone\b/iu', $text)
            || (bool) preg_match('/@\[everyone\]\(everyone\)/iu', $text);

        if (preg_match_all('/(^|[\s(>])@([a-z0-9._-]+)/iu', $text, $handles)) {
            $people = self::peopleByHandle();
            $deptByAlias = self::departmentsByAlias();
            foreach ($handles[2] as $handle) {
                $key = mb_strtolower($handle);
                if ($key === 'everyone') {
                    $wantsEveryone = true;
                    continue;
                }
                $person = $people->get($key);
                if ($person) {
                    $ids[] = (int) $person->id;
                    continue;
                }
                $department = $deptByAlias->get($key);
                if ($department) {
                    foreach (self::userIdsForDepartment((int) $department->id) as $id) {
                        $ids[] = $id;
                    }
                }
            }
        }

        if ($wantsEveryone) {
            foreach (self::everyoneUserIds($ticket) as $id) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->where('status', 'active')
            ->whereIn('id', $ids)
            ->get();
    }

    public static function render(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $escaped = e($text);
        $escaped = preg_replace_callback(
            '/@\[(.*?)\]\((people|user|case|asset|device|server|location|department):(\d+)\)/u',
            function (array $match) {
                $label = $match[1];
                $type = $match[2] === 'user' ? 'people' : $match[2];
                $id = (int) $match[3];
                if ($type === 'department') {
                    $department = Department::query()->find($id);
                    if (! $department) {
                        return e('@'.$label);
                    }
                    $token = '@['.$department->name.'](department:'.$department->id.')';

                    return self::mentionChip('department', '@'.$department->code, $token);
                }

                $url = self::urlFor($type, $id);
                if ($url === null) {
                    return e('@'.$label);
                }

                $prefix = $type === 'people' ? '@' : '';
                $token = '@['.$label.']('.$type.':'.$id.')';

                return self::mentionAnchor($type, $url, $prefix.$label, $token);
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/@\[everyone\]\(everyone\)/iu',
            fn () => self::mentionChip('everyone', '@everyone', '@everyone'),
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(^|[\s(>])@([a-z0-9._-]+)/iu',
            function (array $match) {
                $prefix = $match[1];
                $handle = mb_strtolower($match[2]);
                if ($handle === 'everyone') {
                    return $prefix.self::mentionChip('everyone', '@everyone', '@everyone');
                }

                $person = self::peopleByHandle()->get($handle);
                if ($person) {
                    $token = '@['.$person->name.'](people:'.$person->id.')';

                    return $prefix.self::mentionAnchor('people', route('people.show', $person), '@'.$person->name, $token);
                }

                $department = self::departmentsByAlias()->get($handle);
                if ($department) {
                    $token = '@['.$department->name.'](department:'.$department->id.')';

                    return $prefix.self::mentionChip('department', '@'.$department->code, $token);
                }

                return $match[0];
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(?<![\w\/])#?(PG-\d{4}-\d+)\b/u',
            function (array $match) {
                $number = strtoupper($match[1]);
                $ticket = self::ticketsByNumber()->get($number);
                if (! $ticket) {
                    return e($match[0]);
                }

                return self::mentionAnchor('case', route('helpdesk.show', $ticket), $number, $number);
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(?<![\w\/])#?(PG-[A-Z]+-\d+)\b/u',
            function (array $match) {
                $tag = strtoupper($match[1]);
                if (preg_match('/^PG-\d{4}-\d+$/', $tag)) {
                    return $match[0];
                }
                $asset = self::assetsByTag()->get($tag);
                if (! $asset) {
                    return e($match[0]);
                }

                return self::mentionAnchor('asset', route('assets.show', $asset), $tag, $tag);
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(?<![\w\/])#?(PLT-[A-Z0-9-]+)\b/u',
            function (array $match) {
                $host = strtoupper($match[1]);
                $device = self::devicesByHost()->get($host);
                if ($device) {
                    return self::mentionAnchor('device', route('network.show', $device), $host, $host);
                }
                $server = self::serversByHost()->get($host);
                if ($server) {
                    return self::mentionAnchor('server', route('servers.show', $server), $host, $host);
                }

                return e($match[0]);
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(?<![\w\/#-])#?([A-Z]{2,6}-[A-Z0-9]{2,6})\b/u',
            function (array $match) {
                $code = strtoupper($match[1]);
                if (str_starts_with($code, 'PG-') || str_starts_with($code, 'PLT-')) {
                    return $match[0];
                }
                $location = self::locationsByCode()->get($code);
                if (! $location) {
                    return $match[0];
                }

                $token = '@['.$location->name.'](location:'.$location->id.')';

                return self::mentionAnchor(
                    'location',
                    route('locations.show', $location),
                    $location->name.' ('.$code.')',
                    $token
                );
            },
            $escaped
        ) ?? $escaped;

        $escaped = self::applyInlineMarkup($escaped);

        return nl2br($escaped, false);
    }

    private static function mentionAnchor(string $type, string $url, string $label, string $token): string
    {
        return '<a class="pg-mention pg-mention-'.$type.'" href="'.e($url).'" data-pg-token="'.e($token).'" contenteditable="false">'.e($label).'</a>';
    }

    private static function mentionChip(string $type, string $label, string $token): string
    {
        return '<span class="pg-mention pg-mention-'.$type.'" data-pg-token="'.e($token).'" contenteditable="false">'.e($label).'</span>';
    }

    /**
     * @return list<int>
     */
    private static function everyoneUserIds(?SupportTicket $ticket): array
    {
        if ($ticket) {
            $ticket->loadMissing('partners:id');

            return collect([
                $ticket->requester_id,
                $ticket->assigned_to,
            ])
                ->merge($ticket->partners->pluck('id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return User::query()->where('status', 'active')->limit(200)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return list<int>
     */
    private static function userIdsForDepartment(int $departmentId): array
    {
        return User::query()
            ->where('status', 'active')
            ->where('department_id', $departmentId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<string, Department>
     */
    private static function departmentsByAlias()
    {
        return Cache::remember('richtext.departments.aliases', 30, function () {
            $map = collect();
            foreach (Department::query()->where('status', 'active')->get(['id', 'name', 'code']) as $department) {
                $code = mb_strtolower((string) $department->code);
                $slug = mb_strtolower(str_replace(' ', '_', (string) $department->name));
                $aliases = array_unique(array_filter([
                    $code,
                    $slug,
                    $code.'_department',
                    $slug.'_department',
                ]));

                // Friendly ICT aliases recruiters expect.
                if ($code === 'ict') {
                    $aliases = array_merge($aliases, ['it', 'it_department', 'ict_department']);
                }
                if ($code === 'prd') {
                    $aliases = array_merge($aliases, ['production', 'production_department']);
                }

                foreach ($aliases as $alias) {
                    $map[$alias] = $department;
                }
            }

            return $map;
        });
    }

    private static function applyInlineMarkup(string $text): string
    {
        // Order matters: longer markers before shorter ones.
        $patterns = [
            '/\*\*(.+?)\*\*/us' => '<strong>$1</strong>',
            '/__(.+?)__/us' => '<u>$1</u>',
            '/~~(.+?)~~/us' => '<s>$1</s>',
            '/`(.+?)`/us' => '<code class="pg-inline-code">$1</code>',
            '/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/us' => '<em>$1</em>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        // Simple bullet lines written as "- item"
        $text = preg_replace(
            '/^(\s*)- (.+)$/mu',
            '$1<span class="pg-note-bullet">• $2</span>',
            $text
        ) ?? $text;

        return $text;
    }

    private static function urlFor(string $type, int $id): ?string
    {
        return match ($type) {
            'people' => ($person = User::query()->find($id)) ? route('people.show', $person) : null,
            'case' => ($ticket = SupportTicket::query()->find($id)) ? route('helpdesk.show', $ticket) : null,
            'asset' => ($asset = Asset::query()->find($id)) ? route('assets.show', $asset) : null,
            'device' => ($device = NetworkDevice::query()->find($id)) ? route('network.show', $device) : null,
            'server' => ($server = Server::query()->find($id)) ? route('servers.show', $server) : null,
            'location' => ($location = Location::query()->find($id)) ? route('locations.show', $location) : null,
            default => null,
        };
    }

    /**
     * @return \Illuminate\Support\Collection<string, Location>
     */
    private static function locationsByCode()
    {
        return Cache::remember('richtext.locations.codes', 30, function () {
            return Location::query()
                ->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (Location $location) => [strtoupper((string) $location->code) => $location]);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<string, User>
     */
    private static function peopleByHandle()
    {
        return Cache::remember('richtext.people.handles', 30, function () {
            return User::query()
                ->where('status', 'active')
                ->get(['id', 'name', 'email'])
                ->mapWithKeys(function (User $user) {
                    $handle = mb_strtolower((string) strtok($user->email, '@'));

                    return [$handle => $user];
                });
        });
    }

    /**
     * @return \Illuminate\Support\Collection<string, SupportTicket>
     */
    private static function ticketsByNumber()
    {
        return Cache::remember('richtext.tickets.numbers', 30, function () {
            return SupportTicket::query()
                ->get(['id', 'ticket_number'])
                ->mapWithKeys(fn (SupportTicket $ticket) => [strtoupper($ticket->ticket_number) => $ticket]);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<string, Asset>
     */
    private static function assetsByTag()
    {
        return Cache::remember('richtext.assets.tags', 30, function () {
            return Asset::query()
                ->get(['id', 'asset_tag'])
                ->mapWithKeys(fn (Asset $asset) => [strtoupper($asset->asset_tag) => $asset]);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<string, NetworkDevice>
     */
    private static function devicesByHost()
    {
        return Cache::remember('richtext.devices.hosts', 30, function () {
            return NetworkDevice::query()
                ->get(['id', 'hostname'])
                ->mapWithKeys(fn (NetworkDevice $device) => [strtoupper($device->hostname) => $device]);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<string, Server>
     */
    private static function serversByHost()
    {
        return Cache::remember('richtext.servers.hosts', 30, function () {
            return Server::query()
                ->get(['id', 'hostname'])
                ->mapWithKeys(fn (Server $server) => [strtoupper($server->hostname) => $server]);
        });
    }
}
