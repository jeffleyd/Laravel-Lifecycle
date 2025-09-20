<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\SequentialService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ValidationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\AuditHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\FirstHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\SecondHook;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/Examples/SequentialService.php';
require_once __DIR__ . '/Examples/Hooks/ValidationHook.php';
require_once __DIR__ . '/Examples/Hooks/AuditHook.php';
require_once __DIR__ . '/Examples/Hooks/SequentialHooks.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test hook execution sequence and performance monitoring
 * 
 * This test class covers:
 * - Verifying hook execution order
 * - Performance monitoring (execution time and memory usage)
 */
class HookSequenceAndPerformanceTest extends TestCase
{
    use TestsLifecycleHooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleHooks();
    }

    protected function tearDown(): void
    {
        $this->tearDownLifecycleHooks();
        parent::tearDown();
    }

    /**
     * Test verifying exact hook execution order
     * 
     * Ensures dependencies between hooks are maintained.
     */
    public function test_hooks_execution_order(): void
    {
        $this->captureHookSequence();

        $service = new ExampleService();
        $service->processData(['item1', 'item2']);

        $executedHooks = array_column($this->getHookTrace(), 'hook');
        
        $validationIndex = array_search(ValidationHook::class, $executedHooks);
        $auditIndex = array_search(AuditHook::class, $executedHooks);
        
        $this->assertNotFalse($validationIndex);
        $this->assertNotFalse($auditIndex);
        $this->assertLessThan($auditIndex, $validationIndex);
    }

    /**
     * Test verifying hooks don't execute out of order
     * 
     * Helps detect race condition problems.
     */
    public function test_hooks_do_not_execute_out_of_order(): void
    {
        $this->captureHookSequence();

        $service = new SequentialService();
        $service->runSequence();

        $sequence = $this->getHookTrace();
        $hookNames = array_column($sequence, 'hook');

        $firstIndex = array_search(FirstHook::class, $hookNames);
        $secondIndex = array_search(SecondHook::class, $hookNames);

        $this->assertNotFalse($firstIndex);
        $this->assertNotFalse($secondIndex);
        $this->assertLessThan($secondIndex, $firstIndex);
    }

    /**
     * Test hook execution time performance
     * 
     * Helps detect slow hooks or performance problems.
     */
    public function test_hook_execution_time_performance(): void
    {
        $this->profileHooks();

        $service = new ExampleService();
        $service->processData(['item1', 'item2', 'item3']);

        $metrics = $this->getHookMetrics();

        $this->assertNotEmpty($metrics);

        $totalTime = array_sum(array_column($metrics, 'time'));
        $this->assertLessThan(1000, $totalTime, 'Total execution time too high');
    }

    /**
     * Test hook memory usage
     * 
     * Helps detect memory leaks or excessive usage.
     */
    public function test_hook_memory_usage(): void
    {
        $this->profileHooks();

        $service = new ExampleService();
        $service->processData(['item1', 'item2', 'item3']);

        $metrics = $this->getHookMetrics();

        $this->assertNotEmpty($metrics);

        foreach ($metrics as $hookClass => $metric) {
            $this->assertLessThan(
                10 * 1024 * 1024,
                abs($metric['memory']),
                "Hook {$hookClass} uses too much memory"
            );
        }
    }
}
