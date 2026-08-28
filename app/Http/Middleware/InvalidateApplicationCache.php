<?php

namespace App\Http\Middleware;

use App\Support\ApplicationCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvalidateApplicationCache
{
    public function handle(Request $request, Closure $next, string ...$namespaces): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || ! $response->isSuccessful()) {
            return $response;
        }

        foreach (array_unique($namespaces) as $namespace) {
            if ($namespace === 'settings') {
                ApplicationCache::invalidateSettings();
                ApplicationCache::invalidatePublicResponses('settings');
            } else {
                ApplicationCache::invalidatePublicResponses($namespace);
            }
        }

        return $response;
    }
}
