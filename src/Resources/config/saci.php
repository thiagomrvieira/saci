<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Saci
    |--------------------------------------------------------------------------
    |
    | Enable or disable Saci globally.
    |
    */
    // null -> inherit app.debug
    'enabled' => env('SACI_ENABLED', null),

    /*
    |--------------------------------------------------------------------------
    | Auto Register Middleware
    |--------------------------------------------------------------------------
    |
    | If true, automatically registers the middleware in the 'web' group.
    | If false, you will need to manually register the middleware.
    |
    */
    'auto_register_middleware' => true,

    // Allow AJAX requests to render the bar
    'allow_ajax' => env('SACI_ALLOW_AJAX', false),

    // Optional whitelist of client IPs
    'allow_ips' => array_filter(array_map('trim', explode(',', (string) env('SACI_ALLOW_IPS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Hidden Data Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be hidden from the debugger for security.
    |
    */
    'hide_data_fields' => ['password', 'token', 'secret', 'api_key', 'credentials'],

    // Mask keys (exact or regex) for preview/dumps
    'mask_keys' => [
        'password', 'token', 'secret', 'api_key', 'credentials',
        '/authorization/i', '/cookie/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | Debugger appearance settings.
    |
    */
    'ui' => [
        'position' => 'bottom',
        'theme' => env('SACI_THEME', 'default'),
        'max_height' => '30vh',
        'transparency' => env('SACI_TRANSPARENCY', 1.0),
    ],

    // Dump limits
    'dump' => [
        'max_depth' => env('SACI_DUMP_MAX_DEPTH', 5),
        'max_items' => env('SACI_DUMP_MAX_ITEMS', 100000),
        'max_string' => env('SACI_DUMP_MAX_STRING', 100000),
        // Previews are intentionally smaller
        'preview_max_items' => env('SACI_PREVIEW_MAX_ITEMS', 8),
        'preview_max_string' => env('SACI_PREVIEW_MAX_STRING', 80),
        'preview_max_chars' => env('SACI_PREVIEW_MAX_CHARS', 70),
    ],

    // Per-request caps and TTL for stored dumps
    'caps' => [
        'per_request_bytes' => env('SACI_PER_REQUEST_BYTES', 1048576), // 1MB
        'ttl_seconds' => env('SACI_DUMP_TTL', 60),
    ],

    // Optional CSP nonce to apply to scripts
    'csp_nonce' => env('SACI_CSP_NONCE', null),

    /*
    |--------------------------------------------------------------------------
    | Performance Tracking
    |--------------------------------------------------------------------------
    |
    | Enable or disable view loading time tracking.
    |
    */
    'track_performance' => env('SACI_TRACK_PERFORMANCE', true)
];