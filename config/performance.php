<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application response caches
    |--------------------------------------------------------------------------
    |
    | These caches work with every Laravel cache store, including the current
    | file store and Memcached when it is enabled later on the server.
    |
    */
    'public_api_cache_ttl' => (int) env('PUBLIC_API_CACHE_TTL', 86400),
    'settings_cache_ttl' => (int) env('SETTINGS_CACHE_TTL', 600),
];
