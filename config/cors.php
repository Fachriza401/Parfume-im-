<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Parfum IM is consumed by a native mobile client, which is not subject to
    | browser CORS rules. The settings below are permissive for local
    | development only. In production, set ALLOWED_ORIGINS to the exact web
    | origins that are permitted.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', (string) env('ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('SANCTUM_STATEFUL_DOMAINS'),

];
