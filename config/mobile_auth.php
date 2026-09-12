<?php

return [
    'enabled' => env('MOBILE_AUTH_ENABLED', true),
    'mail_enabled' => env('MOBILE_AUTH_MAIL_ENABLED', false),
    'token_days' => 30,
    'google' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', env('MOBILE_GOOGLE_CLIENT_IDS', ''))))),
        'server_client_id' => env('MOBILE_GOOGLE_SERVER_CLIENT_ID', ''),
        'ios_client_id' => env('MOBILE_GOOGLE_IOS_CLIENT_ID', ''),
    ],
    'apple' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', env('MOBILE_APPLE_CLIENT_IDS', ''))))),
        'client_id' => env('MOBILE_APPLE_SERVICE_ID', ''),
        'team_id' => env('MOBILE_APPLE_TEAM_ID', ''),
        'key_id' => env('MOBILE_APPLE_KEY_ID', ''),
        'private_key_path' => env('MOBILE_APPLE_PRIVATE_KEY_PATH', ''),
        'redirect_uri' => env('MOBILE_APPLE_REDIRECT_URI', ''),
        'android_package' => env('MOBILE_APPLE_ANDROID_PACKAGE', 'net.salatime.app'),
    ],
];
