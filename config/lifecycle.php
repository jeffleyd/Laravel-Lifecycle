<?php

return [
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