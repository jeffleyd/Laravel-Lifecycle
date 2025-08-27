<?php

namespace App\Hooks;

/**
 * Example Kernel showing how to register hooks
 * 
 * Copy this to app/Hooks/Kernel.php in your Laravel application
 */
class ExampleKernel
{
    /**
     * Hook registration and execution order.
     * 
     * Register all your hooks here. The system no longer uses auto-discovery.
     * All hooks must be explicitly registered in this Kernel.
     */
    public array $hooks = [
        // PaymentService hooks
        \App\Services\PaymentService::class => [
            'before_payment' => [
                \App\Hooks\FraudDetectionHook::class,      // Critical - runs first
                \App\Hooks\ValidateAmountHook::class,      // Critical - validates amount
            ],
            'after_payment' => [
                \App\Hooks\PaymentEmailHook::class,        // Optional - send email
                \App\Hooks\PaymentAnalyticsHook::class,    // Optional - track analytics
            ],
            'payment_failed' => [
                \App\Hooks\PaymentFailureNotificationHook::class, // Optional - notify failure
            ],
        ],
        
        // OrderService hooks (example mixing old and new styles)
        \App\Services\OrderService::class => [
            'before_create' => [
                \App\Hooks\OrderService\InventoryCheckHook::class,    // Old-style interface
                \App\Hooks\ValidateOrderHook::class,                  // New-style attribute
            ],
            'after_create' => [
                \App\Hooks\OrderService\SendConfirmationHook::class,  // Old-style interface
                \App\Hooks\OrderAnalyticsHook::class,                 // New-style attribute
            ],
        ],
        
        // UserService hooks
        \App\Services\UserService::class => [
            'user_created' => [
                \App\Hooks\UserService\ValidateDataHook::class,       // Any style
                \App\Hooks\WelcomeEmailHook::class,                   // Any style
                \External\Package\Hooks\UserAnalyticsHook::class,     // External packages
            ],
            'user_updated' => [
                \App\Hooks\UserService\CacheInvalidationHook::class,  // Any style
            ],
        ],
    ];
}
