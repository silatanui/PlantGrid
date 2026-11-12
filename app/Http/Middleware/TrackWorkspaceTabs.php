<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceTabService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackWorkspaceTabs
{
    public function __construct(private WorkspaceTabService $tabs)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->isMethod('GET') && ! $request->ajax() && ! $request->expectsJson()) {
            $this->tabs->touchFromRequest($request);
        }

        return $next($request);
    }
}
