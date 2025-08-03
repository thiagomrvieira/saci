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
    'enabled' => env('SACI_ENABLED', true),

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

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | List of environments where Saci should be active.
    |
    */
    'environments' => ['local', 'development'],

    /*
    |--------------------------------------------------------------------------
    | Hidden Data Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should be hidden from the debugger for security.
    |
    */
    'hide_data_fields' => ['password', 'token', 'secret', 'api_key', 'credentials'],

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
        'theme' => 'dark',
        'max_height' => '30vh'
    ]
];