<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\NetworkDevice;
use App\Models\Server;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RichText
{
    /**
     * Active users explicitly addressed in note text (@handle or @[Name](people:id)).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function mentionedUsers(?string $text)
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

        if (preg_match_all('/(^|[\s(>])@([a-z0-9._-]+)/iu', $text, $handles)) {
            $people = self::peopleByHandle();
            foreach ($handles[2] as $handle) {
                $person = $people->get(mb_strtolower($handle));
                if ($person) {
                    $ids[] = (int) $person->id;
                }
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
            '/@\[(.*?)\]\((people|user|case|asset|device|server):(\d+)\)/u',
            function (array $match) {
                $label = $match[1];
                $type = $match[2] === 'user' ? 'people' : $match[2];
                $id = (int) $match[3];
                $url = self::urlFor($type, $id);

                if ($url === null) {
                    return e('@'.$label);
                }

                $prefix = $type === 'people' ? '@' : '';

                return '<a class="pg-mention pg-mention-'.$type.'" href="'.e($url).'">'.$prefix.e($label).'</a>';
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(^|[\s(>])@([a-z0-9._-]+)/iu',
            function (array $match) {
                $prefix = $match[1];
                $handle = $match[2];
                $person = self::peopleByHandle()->get(mb_strtolower($handle));
                if (! $person) {
                    return $match[0];
                }

                return $prefix.'<a class="pg-mention pg-mention-people" href="'.e(route('people.show', $person)).'">@'.e($person->name).'</a>';
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

                return '<a class="pg-mention pg-mention-case" href="'.e(route('helpdesk.show', $ticket)).'">'.e($number).'</a>';
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

                return '<a class="pg-mention pg-mention-asset" href="'.e(route('assets.show', $asset)).'">'.e($tag).'</a>';
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(?<![\w\/])#?(PLT-[A-Z0-9-]+)\b/u',
            function (array $match) {
                $host = strtoupper($match[1]);
                $device = self::devicesByHost()->get($host);
                if ($device) {
                    return '<a class="pg-mention pg-mention-device" href="'.e(route('network.show', $device)).'">'.e($host).'</a>';
                }
                $server = self::serversByHost()->get($host);
                if ($server) {
                    return '<a class="pg-mention pg-mention-server" href="'.e(route('servers.show', $server)).'">'.e($host).'</a>';
                }

                return e($match[0]);
            },
            $escaped
        ) ?? $escaped;

        $escaped = self::applyInlineMarkup($escaped);

        return nl2br($escaped, false);
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
            default => null,
        };
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
