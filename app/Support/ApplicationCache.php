<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ApplicationCache
{
    private const SCHEMA_VERSION = 'v1';

    public static function publicResponseKey(Request $request, string $namespace): string
    {
        $route = $request->route();
        $parameters = [];

        if ($route) {
            foreach ($route->parameters() as $name => $value) {
                $parameters[$name] = self::normalizeRouteParameter($value);
            }
            ksort($parameters);
        }

        $signature = [
            'route' => $route?->uri(),
            'name' => $route?->getName(),
            'path' => $request->getPathInfo(),
            'parameters' => $parameters,
            'query' => self::normalizeArray($request->query()),
            'locale' => app()->getLocale(),
        ];

        return sprintf(
            'salatime:public-api:%s:%s:%s',
            self::SCHEMA_VERSION,
            self::namespaceVersion("public-api:{$namespace}"),
            hash('sha256', json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        );
    }

    public static function settingsKey(string $context): string
    {
        return sprintf(
            'salatime:settings:%s:%s:%s',
            self::SCHEMA_VERSION,
            self::namespaceVersion('settings'),
            hash('sha256', $context)
        );
    }

    public static function invalidatePublicResponses(string $namespace): void
    {
        self::invalidateNamespace("public-api:{$namespace}");
    }

    public static function invalidateSettings(): void
    {
        self::invalidateNamespace('settings');
    }

    private static function namespaceVersion(string $namespace): string
    {
        $key = self::versionKey($namespace);
        $version = Cache::get($key);

        if (is_string($version) && $version !== '') {
            return $version;
        }

        Cache::forever($key, 'initial');

        return 'initial';
    }

    private static function invalidateNamespace(string $namespace): void
    {
        Cache::forever(self::versionKey($namespace), (string) Str::uuid());
    }

    private static function versionKey(string $namespace): string
    {
        return sprintf(
            'salatime:cache-version:%s:%s',
            self::SCHEMA_VERSION,
            hash('sha256', $namespace)
        );
    }

    private static function normalizeRouteParameter(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $value->getRouteKey();
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return is_scalar($value) || $value === null ? $value : get_debug_type($value);
    }

    private static function normalizeArray(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::normalizeArray($value);
            }
        }

        return $values;
    }
}
