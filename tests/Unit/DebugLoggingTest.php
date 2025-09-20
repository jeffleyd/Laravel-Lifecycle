<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;

class DebugLoggingTest extends TestCase
{
    private array $logs = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container->bind('config', function () {
            return new class {
                public function get($key, $default = null) {
                    return match ($key) {
                        'lifecycle.debug',
                        'lifecycle.error_handling.log_failures',
                        'lifecycle.error_handling.throw_on_critical' => true,
                        default => $default
                    };
                }
            };
        });

        $logs = &$this->logs;
        $this->container->bind('log', function () use (&$logs) {
            return new class($logs) {
                private array $logs;
                
                public function __construct(array &$logs)
                {
                    $this->logs = &$logs;
                }
                
                public function debug($message, $context = []): void
                {
                    $this->logs[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
                }
                
                public function error($message, $context = []): void
                {
                    $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
                }
            };
        });
    }

    /**
     * @throws InvalidLifeCycleException
     * @throws HookExecutionException
     */
    public function test_debug_logs_lifecycle_execution_flow(): void
    {
        $discountHook = new DebugDiscountHook();
        $this->manager->setHooksFor(DebugPaymentService::class, collect([$discountHook]));
        
        $amount = 1000.0;
        $userId = 123;
        
        $this->manager->runHook(DebugPaymentService::class, 'process_payment', $amount, $userId);
        
        $this->assertGreaterThan(0, count($this->logs));
        $startLogs = array_filter($this->logs, fn($log) => str_contains($log['message'], 'started'));
        $this->assertCount(1, $startLogs);
        
        $startLog = array_values($startLogs)[0];
        $this->assertStringContainsString('DebugPaymentService', $startLog['message']);
        $this->assertStringContainsString('process_payment', $startLog['message']);
        $this->assertEquals(1, $startLog['context']['hooks_count']);
        
        $hookExecutionLogs = array_filter($this->logs, fn($log) => str_contains($log['message'], '[Hook]'));
        $this->assertGreaterThan(0, count($hookExecutionLogs));
        
        $hookStartLogs = array_filter($hookExecutionLogs, fn($log) => str_contains($log['message'], 'executing'));
        $this->assertCount(1, $hookStartLogs);
        
        $hookStartLog = array_values($hookStartLogs)[0];
        $this->assertStringContainsString('PhpDiffused\Lifecycle\Tests\Unit\DebugDiscountHook', $hookStartLog['message']);
        $this->assertArrayHasKey('variables_before', $hookStartLog['context']);
        
        $hookEndLogs = array_filter($hookExecutionLogs, fn($log) => str_contains($log['message'], 'completed'));
        $this->assertCount(1, $hookEndLogs);
        
        $hookEndLog = array_values($hookEndLogs)[0];
        $this->assertStringContainsString('PhpDiffused\Lifecycle\Tests\Unit\DebugDiscountHook', $hookEndLog['message']);
        $this->assertArrayHasKey('variables_after', $hookEndLog['context']);
        $this->assertArrayHasKey('changes_detected', $hookEndLog['context']);
        
        $endLogs = array_filter($this->logs, fn($log) => str_contains($log['message'], 'completed'));
        $this->assertGreaterThan(0, count($endLogs));
    }
    
    public function test_debug_logs_show_variable_changes(): void
    {
        $modifyHook = new DebugModifyHook();
        $this->manager->setHooksFor(DebugPaymentService::class, collect([$modifyHook]));
        
        $amount = 100.0;
        $userId = 123;
        
        $this->manager->runHook(DebugPaymentService::class, 'process_payment', $amount, $userId);
        
        $hookCompletedLogs = array_filter($this->logs, fn($log) =>
            str_contains($log['message'], '[Hook]') && 
            str_contains($log['message'], 'completed')
        );
        
        $this->assertCount(1, $hookCompletedLogs);
        
        $hookLog = array_values($hookCompletedLogs)[0];
        $this->assertArrayHasKey('changes_detected', $hookLog['context']);
        $this->assertArrayHasKey('changes', $hookLog['context']);
        $this->assertTrue(true);
    }
    
    public function test_debug_disabled_does_not_generate_logs(): void
    {
        $this->container->bind('config', function () {
            return new class {
                public function get($key, $default = null) {
                    return match ($key) {
                        'lifecycle.debug' => false,                         'lifecycle.error_handling.log_failures' => true,
                        'lifecycle.error_handling.throw_on_critical' => true,
                        default => $default
                    };
                }
            };
        });
        
        $hook = new DebugDiscountHook();
        $this->manager->setHooksFor(DebugPaymentService::class, collect([$hook]));
        
        $amount = 1000.0;
        $userId = 123;
        
        $this->manager->runHook(DebugPaymentService::class, 'process_payment', $amount, $userId);
        
        $debugLogs = array_filter($this->logs, fn($log) => $log['level'] === 'debug');
        $this->assertCount(0, $debugLogs);
    }
}

#[LifeCyclePoint('process_payment', ['amount', 'user_id'])]
class DebugPaymentService
{
    use HasLifecycle;
}

#[Hook(scope: 'payment', point: 'process_payment', severity: Severity::Optional)]
class DebugDiscountHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
    }
}

#[Hook(scope: 'payment', point: 'process_payment', severity: Severity::Optional)]
class DebugModifyHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args[0] = $args[0] * 0.9;
    }
}
