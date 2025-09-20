<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\SequentialService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\RecursiveService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\InvalidService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ThirdHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\FourthHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\RecursiveHook;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/Examples/SequentialService.php';
require_once __DIR__ . '/Examples/RecursiveService.php';
require_once __DIR__ . '/Examples/InvalidService.php';
require_once __DIR__ . '/Examples/Hooks/SequentialHooks.php';
require_once __DIR__ . '/Examples/Hooks/RecursiveHook.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test advanced hook scenarios and edge cases
 * 
 * This test class covers complex scenarios:
 * - Integration testing with multiple services
 * - Exception handling in hook sequences
 * - Recursive hook execution
 * - Error handling for invalid lifecycle usage
 */
class AdvancedHookScenariosTest extends TestCase
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
     * Test complete integration flow
     * 
     * Simulates a real-world scenario with multiple operations.
     */
    public function test_complete_flow_integration(): void
    {
        $this->captureHookSequence();

        $service = new ExampleService();
        
        $processResult = $service->processData(['order1', 'order2']);
        $this->assertTrue($processResult->processed);

        $calcResult = $service->calculateAmount(100.00);
        $this->assertEquals(100.00, $calcResult->original);

        $notifyResult = $service->sendNotification('Order processed', 'user@test.com');
        $this->assertTrue($notifyResult);

        $trace = $this->getHookTrace();
        $this->assertGreaterThan(3, count($trace));
    }

    /**
     * Test exception handling in hook sequence
     * 
     * Ensures that critical hook failures stop execution properly.
     */
    public function test_exception_in_middle_of_hook_sequence(): void
    {
        $this->mockHook(ThirdHook::class)
            ->shouldFail(new \Exception('Critical failure'));

        $this->expectException(\Exception::class);

        $service = new SequentialService();
        $service->runSequence();

        $this->assertNotExecuted(FourthHook::class);
    }

    /**
     * Test recursive hook execution
     * 
     * Ensures the system handles recursive calls correctly.
     */
    public function test_prevent_recursive_hook_execution(): void
    {
        $spy = $this->spyHook(RecursiveHook::class);

        $service = new RecursiveService();
        $service->triggerRecursion(3);

        $spy->assertExecutedTimes(3);
    }

    /**
     * Test error handling for invalid lifecycle usage
     * 
     * Ensures proper error messages for misconfigured services.
     */
    public function test_hook_without_lifecycle_point(): void
    {
        $this->expectException(InvalidLifeCycleException::class);

        $service = new InvalidService();
        $service->doSomething();
    }
}
