<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\NetworkDevice;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\DocumentationCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WorkspaceTabService
{
    private const SESSION_KEY = 'workspace.tabs';

    private const MAX_TABS = 12;

    /** Module hubs already live in primary nav — never keep them as record tabs. */
    private const MODULE_KEYS = ['messages', 'docs', 'monitoring'];

    public function all(): Collection
    {
        return collect(session(self::SESSION_KEY, []))
            ->reject(fn (array $tab) => in_array($tab['key'] ?? '', self::MODULE_KEYS, true)
                || in_array($tab['type'] ?? '', self::MODULE_KEYS, true))
            ->values();
    }

    public function close(string $key): void
    {
        $tabs = $this->all()
            ->reject(fn (array $tab) => ($tab['key'] ?? '') === $key)
            ->values()
            ->all();

        session([self::SESSION_KEY => $tabs]);
    }

    /**
     * @param  list<string>  $keys
     */
    public function reorder(array $keys): void
    {
        $byKey = $this->all()->keyBy(fn (array $tab) => (string) ($tab['key'] ?? ''));
        $ordered = collect($keys)
            ->map(fn ($key) => $byKey->get((string) $key))
            ->filter()
            ->values();

        $remaining = $this->all()
            ->reject(fn (array $tab) => in_array((string) ($tab['key'] ?? ''), $keys, true))
            ->values();

        session([self::SESSION_KEY => $ordered->concat($remaining)->values()->all()]);
    }

    public function touchFromRequest(Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        // Only open *record* tabs. Module pages (Messages, Docs index, Monitoring)
        // are reached from primary navigation and must not duplicate there.
        if ($request->routeIs('helpdesk.show')) {
            $ticket = $request->route('ticket');
            if ($ticket instanceof SupportTicket) {
                $this->open([
                    'key' => 'case:'.$ticket->id,
                    'type' => 'case',
                    'label' => $ticket->ticket_number,
                    'meta' => 'Case',
                    'icon' => 'life-buoy',
                    'url' => route('helpdesk.show', $ticket),
                ]);
            }

            return;
        }

        if ($request->routeIs('people.show')) {
            $person = $request->route('person');
            if ($person instanceof User) {
                $this->open([
                    'key' => 'contact:'.$person->id,
                    'type' => 'contact',
                    'label' => $person->name,
                    'meta' => 'Contact',
                    'icon' => 'user',
                    'url' => route('people.show', $person),
                ]);
            }

            return;
        }

        if ($request->routeIs('docs.show')) {
            $slug = (string) $request->route('slug');
            $doc = DocumentationCatalog::find($slug);
            if ($doc) {
                $this->open([
                    'key' => 'doc:'.$slug,
                    'type' => 'doc',
                    'label' => $doc['title'],
                    'meta' => 'Doc',
                    'icon' => 'book-open',
                    'url' => route('docs.show', $slug),
                ]);
            }

            return;
        }

        if ($request->routeIs('assets.show')) {
            $asset = $request->route('asset');
            if ($asset instanceof Asset) {
                $this->open([
                    'key' => 'asset:'.$asset->id,
                    'type' => 'asset',
                    'label' => $asset->asset_tag,
                    'meta' => 'Asset',
                    'icon' => 'boxes',
                    'url' => route('assets.show', $asset),
                ]);
            }

            return;
        }

        if ($request->routeIs('network.show')) {
            $device = $request->route('device');
            if ($device instanceof NetworkDevice) {
                $this->open([
                    'key' => 'network:'.$device->id,
                    'type' => 'network',
                    'label' => $device->hostname,
                    'meta' => 'Network',
                    'icon' => 'network',
                    'url' => route('network.show', $device),
                ]);
            }
        }
    }

    public function open(array $tab): void
    {
        if (in_array($tab['key'] ?? '', self::MODULE_KEYS, true)
            || in_array($tab['type'] ?? '', self::MODULE_KEYS, true)) {
            return;
        }

        $tabs = $this->all();
        $index = $tabs->search(fn (array $existing) => ($existing['key'] ?? '') === ($tab['key'] ?? null));

        if ($index !== false) {
            $tabs[$index] = array_merge($tabs[$index], $tab);
        } else {
            $tabs->push($tab);
            if ($tabs->count() > self::MAX_TABS) {
                $tabs = $tabs->slice($tabs->count() - self::MAX_TABS)->values();
            }
        }

        session([self::SESSION_KEY => $tabs->values()->all()]);
    }

    public function isActive(array $tab, Request $request): bool
    {
        return match ($tab['type'] ?? '') {
            'doc' => $request->routeIs('docs.show')
                && (string) $request->route('slug') === (string) Str::after($tab['key'] ?? '', 'doc:'),
            'case' => $request->routeIs('helpdesk.show')
                && (string) optional($request->route('ticket'))->getKey() === (string) Str::after($tab['key'] ?? '', 'case:'),
            'contact' => $request->routeIs('people.show')
                && (string) optional($request->route('person'))->getKey() === (string) Str::after($tab['key'] ?? '', 'contact:'),
            'asset' => $request->routeIs('assets.show')
                && (string) optional($request->route('asset'))->getKey() === (string) Str::after($tab['key'] ?? '', 'asset:'),
            'network' => $request->routeIs('network.show')
                && (string) optional($request->route('device'))->getKey() === (string) Str::after($tab['key'] ?? '', 'network:'),
            default => rtrim($request->url(), '/') === rtrim((string) ($tab['url'] ?? ''), '/'),
        };
    }
}
