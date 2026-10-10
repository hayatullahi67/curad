<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without this, the browser's back/forward cache can restore a previous
 * page's rendered HTML after the session's authenticated user has changed
 * (vendor -> staff via "Login as Staff", staff -> vendor via "Return to
 * Admin", or a plain logout) — the switch is correct server-side, but
 * pressing Back shows a stale screenshot that looks like the old session
 * is still active until the next real request.
 */
class PreventBackHistoryCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
