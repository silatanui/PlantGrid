<?php

namespace App\Http\Middleware;

use App\Services\DemoTimelineService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RefreshDemoTimeline
{
    public function __construct(private readonly DemoTimelineService $timeline)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            try {
                $this->timeline->refreshOncePerDay();
            } catch (Throwable) {
                // Demo convenience only - never block the console.
            }
        }

        return $next($request);
    }
}
