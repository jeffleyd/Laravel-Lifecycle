<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples;

use PhpDiffused\Lifecycle\Traits\HasLifecycle;

/**
 * Example service demonstrating lifecycle hook usage
 * 
 * This service simulates a typical business service with various
 * lifecycle points where hooks can be attached for validation,
 * logging, transformation, and other cross-cutting concerns.
 */
class ExampleService
{
    use HasLifecycle;

    /**
     * Define the lifecycle points for this service
     */
    public static function lifeCycle(): array
    {
        return [
            'before_process' => ['data', 'options'],
            'after_process' => ['data', 'result'],
            'before_validate' => ['data'],
            'after_validate' => ['data'],
            'before_calculate' => ['amount', 'result'],
            'after_calculate' => ['amount', 'result'],
            'before_notify' => ['message', 'recipient'],
            'after_notify' => ['message', 'recipient'],
        ];
    }

    /**
     * Process data with validation and transformation hooks
     */
    public function processData(array $data, array $options = []): object
    {
        runHook(self::class, 'before_validate', $data);
        runHook(self::class, 'after_validate', $data);

        runHook(self::class, 'before_process', $data, $options);
        
        $result = [
            'processed' => true,
            'items' => count($data),
            'timestamp' => time(),
            'options' => $options
        ];

        runHook(self::class, 'after_process', $data, $result);

        return (object) $result;
    }

    /**
     * Calculate amount with hooks for business rules
     */
    public function calculateAmount(float $amount): object
    {
        $result = $amount;
        
        runHook(self::class, 'before_calculate', $amount, $result);
        runHook(self::class, 'after_calculate', $amount, $result);
        
        return (object) [
            'original' => $amount,
            'calculated' => $result
        ];
    }

    /**
     * Send notification with hooks for message processing
     */
    public function sendNotification(string $message, string $recipient): bool
    {
        runHook(self::class, 'before_notify', $message, $recipient);
        runHook(self::class, 'after_notify', $message, $recipient);
        
        return true;
    }

    /**
     * Simple action for basic testing
     */
    public function simpleAction(): string
    {
        $data = ['action' => 'simple'];
        runHook(self::class, 'before_validate', $data);
        return 'completed';
    }
}
