<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRobotsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Global middleware also sees rendered errors for unmatched API paths
        // and unsupported methods, before the route middleware group runs.
        if ($request->is('api', 'api/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
