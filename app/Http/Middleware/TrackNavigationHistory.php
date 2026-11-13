<?php

namespace App\Http\Middleware;

use App\Services\NavigationHistory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackNavigationHistory
{
    public function __construct(private NavigationHistory $history)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $this->history->shouldTrack($request)
            && $response->getStatusCode() < 400
            && $this->isHtmlResponse($response)
        ) {
            $this->history->push($request);
        }

        return $response;
    }

    private function isHtmlResponse(Response $response): bool
    {
        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' || str_contains($type, 'text/html');
    }
}
