<?php

namespace App\Http\Middleware;

use App\Services\LivePulse\LivePulseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CatchUpLivePulse
{
    public function __construct(private readonly LivePulseService $pulse)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            try {
                Cache::lock('plantgrid.live_pulse.catchup', 20)->get(function (): void {
                    $this->pulse->catchUpIfStale();
                });
            } catch (Throwable) {
                // Demo convenience only — never block the console.
            }
        }

        return $next($request);
    }
}
