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
        \App\Services\PaymentService::class => [
            'before_payment' => [
                \App\Hooks\FraudDetectionHook::class,      // Critical - runs first
            ],
            'after_payment' => [
                \App\Hooks\PaymentEmailHook::class,        // Optional - send email
                \App\Hooks\PaymentAnalyticsHook::class,    // Optional - track analytics
            ],
            'payment_failed' => [
                \App\Hooks\PaymentFailureNotificationHook::class, // Optional - notify failure
            ],
        ],
    ];
}
