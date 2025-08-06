<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auto Discovery
    |--------------------------------------------------------------------------
    |
    | This option controls whether the package should automatically discover
    | and register hooks from the filesystem. When disabled, you must manually
    | register hooks in your service providers.
    |
    */
    'auto_discovery' => env('LIFECYCLE_AUTO_DISCOVERY', true),

    /*
    |--------------------------------------------------------------------------
    | Hook Discovery Path
    |--------------------------------------------------------------------------
    |
    | When auto-discovery is enabled, the package will look for hooks in this
    | directory. By default, it looks in app/Hooks/{ServiceClassName}/
    |
    */
    'discovery_path' => env('LIFECYCLE_DISCOVERY_PATH', app_path('Hooks')),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Enable caching of discovered hooks for better performance in production.
    | The cache will be automatically cleared when hooks are added or removed.
    |
    */
    'cache' => [
        'enabled' => env('LIFECYCLE_CACHE_ENABLED', env('APP_ENV') === 'production'),
        'key' => env('LIFECYCLE_CACHE_KEY', 'lifecycle.hooks'),
        'ttl' => env('LIFECYCLE_CACHE_TTL', 86400), // 24 hours in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure how the package handles errors in hooks.
    |
    */
    'error_handling' => [
        'log_failures' => env('LIFECYCLE_LOG_FAILURES', true),
        'throw_on_critical' => env('LIFECYCLE_THROW_ON_CRITICAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, provides detailed debugging information about hook execution.
    |
    */
    'debug' => env('LIFECYCLE_DEBUG', env('APP_DEBUG', false)),
];