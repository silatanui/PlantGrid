<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WorkspaceTabService
{
    private const SESSION_KEY = 'workspace.tabs';

    private const MAX_TABS = 10;

    public function all(): Collection
    {
        return collect(session(self::SESSION_KEY, []))->values();
    }

    public function close(string $key): void
    {
        $tabs = $this->all()
            ->reject(fn (array $tab) => ($tab['key'] ?? '') === $key)
            ->values()
            ->all();

        session([self::SESSION_KEY => $tabs]);
    }

    public function touchFromRequest(Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

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

        if ($request->routeIs('messages.*')) {
            $this->open([
                'key' => 'messages',
                'type' => 'messages',
                'label' => 'Messages',
                'meta' => 'Inbox',
                'icon' => 'message-square',
                'url' => route('messages.index'),
            ]);
        }
    }

    public function open(array $tab): void
    {
        $tabs = $this->all()
            ->reject(fn (array $existing) => ($existing['key'] ?? '') === $tab['key'])
            ->push($tab)
            ->take(-1 * self::MAX_TABS)
            ->values()
            ->all();

        session([self::SESSION_KEY => $tabs]);
    }

    public function isActive(array $tab, Request $request): bool
    {
        return match ($tab['type'] ?? '') {
            'messages' => $request->routeIs('messages.*'),
            'case' => $request->routeIs('helpdesk.show')
                && (string) optional($request->route('ticket'))->getKey() === (string) Str::after($tab['key'] ?? '', 'case:'),
            'contact' => $request->routeIs('people.show')
                && (string) optional($request->route('person'))->getKey() === (string) Str::after($tab['key'] ?? '', 'contact:'),
            default => rtrim($request->url(), '/') === rtrim((string) ($tab['url'] ?? ''), '/'),
        };
    }
}
