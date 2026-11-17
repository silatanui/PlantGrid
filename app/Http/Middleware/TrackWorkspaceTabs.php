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
        // Shell soft-navigation fetches full HTML pages with X-Requested-With; they are real page opens.
        $isShellNav = $request->header('X-PlantGrid-Shell-Nav') === '1';

        if (
            $request->user()
            && $request->isMethod('GET')
            && ($isShellNav || ! $request->ajax())
            && ! $request->expectsJson()
        ) {
            $this->tabs->touchFromRequest($request);
        }

        return $next($request);
    }
}
