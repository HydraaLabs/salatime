<?php

namespace App\Http\Middleware;

use App\Support\ApplicationCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CachePublicApiResponse
{
    public function handle(
        Request $request,
        Closure $next,
        string $namespace = 'content',
        int|string|null $ttl = null
    ): Response {
        if (! $this->isCacheableRequest($request)) {
            return $next($request);
        }

        $key = ApplicationCache::publicResponseKey($request, $namespace);
        $cached = Cache::get($key);

        if (is_array($cached) && isset($cached['body'], $cached['status'], $cached['headers'])) {
            $response = response($cached['body'], $cached['status'], $cached['headers'])
                ->header('X-SalaTime-Cache', 'HIT');

            $response->isNotModified($request);

            return $response;
        }

        $response = $next($request);

        if (! $this->isCacheableResponse($response)) {
            return $response;
        }

        $ttl = max(1, (int) ($ttl ?? config('performance.public_api_cache_ttl', 86400)));

        $response->setPublic();
        $response->setMaxAge($ttl);
        $response->setSharedMaxAge($ttl);
        $response->setEtag(md5((string) $response->getContent()));

        Cache::put($key, [
            'body' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $this->cacheableHeaders($response),
        ], $ttl);

        $response->isNotModified($request);

        return $response->header('X-SalaTime-Cache', 'MISS');
    }

    private function isCacheableRequest(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->headers->has('Authorization')) {
            return false;
        }

        return $request->user() === null;
    }

    private function isCacheableResponse(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        $cacheControl = (string) $response->headers->get('Cache-Control');

        return str_contains(strtolower($contentType), 'application/json')
            && ! str_contains(strtolower($cacheControl), 'no-store')
            && ! $response->headers->has('Set-Cookie');
    }

    private function cacheableHeaders(Response $response): array
    {
        return array_filter([
            'Content-Type' => $response->headers->get('Content-Type'),
            'Cache-Control' => $response->headers->get('Cache-Control'),
            'ETag' => $response->headers->get('ETag'),
        ], static fn ($value) => is_string($value) && $value !== '');
    }
}
