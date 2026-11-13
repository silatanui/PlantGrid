<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NavigationHistory
{
    public const SESSION_KEY = 'nav.stack';

    private const MAX = 15;

    /**
     * @return Collection<int, array{url: string, path: string, label: string}>
     */
    public function stack(): Collection
    {
        return collect(session(self::SESSION_KEY, []))
            ->filter(fn ($entry) => is_array($entry) && filled($entry['url'] ?? null))
            ->values();
    }

    /**
     * Previous in-app page (not the current one).
     *
     * @return array{url: string, path: string, label: string}|null
     */
    public function previous(?Request $request = null): ?array
    {
        $request ??= request();
        $current = $this->normalize($request->fullUrl());
        $stack = $this->stack();

        for ($i = $stack->count() - 1; $i >= 0; $i--) {
            $entry = $stack[$i];
            if ($this->normalize($entry['url']) !== $current && $this->isSameApp($entry['url'], $request)) {
                return $entry;
            }
        }

        return null;
    }

    public function previousUrl(?Request $request = null): ?string
    {
        return $this->previous($request)['url'] ?? null;
    }

    public function push(Request $request): void
    {
        if (! $this->shouldTrack($request)) {
            return;
        }

        $url = $request->fullUrl();
        if (! $this->isSameApp($url, $request)) {
            return;
        }

        $entry = [
            'url' => $url,
            'path' => '/'.ltrim($request->path(), '/'),
            'label' => $this->labelFor($request),
        ];

        $stack = $this->stack();
        $top = $stack->last();
        if ($top && $this->normalize($top['url']) === $this->normalize($url)) {
            return;
        }

        $stack->push($entry);
        if ($stack->count() > self::MAX) {
            $stack = $stack->slice($stack->count() - self::MAX)->values();
        }

        session([self::SESSION_KEY => $stack->values()->all()]);
    }

    public function shouldTrack(Request $request): bool
    {
        if (! $request->user()) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return false;
        }

        if ($request->routeIs('login', 'login.store', 'logout', 'presence.*', 'live.*', 'mentions.*', 'workspace.*')) {
            return false;
        }

        $path = $request->path();
        if (Str::startsWith($path, ['build/', 'storage/', 'up'])) {
            return false;
        }

        return true;
    }

    public function isSameApp(string $url, ?Request $request = null): bool
    {
        $request ??= request();
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === '') {
            return Str::startsWith($url, '/');
        }

        return strcasecmp((string) $host, (string) $request->getHost()) === 0;
    }

    public function labelFor(Request $request): string
    {
        $name = $request->route()?->getName();

        return match (true) {
            $name === 'dashboard' => 'Dashboard',
            $name === 'helpdesk.index' => 'Cases',
            $name === 'helpdesk.show' => 'Case',
            $name === 'assets.index' => 'Assets',
            $name === 'assets.show' => 'Asset',
            $name === 'network.index' => 'Network',
            $name === 'network.show' => 'Network device',
            $name === 'servers.index' => 'Servers',
            $name === 'servers.show' => 'Server',
            $name === 'locations.index' => 'Plant map',
            $name === 'locations.show' => 'Location',
            $name === 'reports.index' => 'Reports',
            $name === 'messages.index' => 'Messages',
            $name === 'docs.index' => 'Documentation',
            $name === 'people.show' => 'Contact',
            default => Str::of($request->path())->replace('/', ' · ')->title()->toString() ?: 'Previous page',
        };
    }

    private function normalize(string $url): string
    {
        return rtrim($url, '/');
    }
}
