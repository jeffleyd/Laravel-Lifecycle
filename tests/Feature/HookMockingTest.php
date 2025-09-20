<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ValidationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\LoggingHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\CalculationHook;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/Examples/Hooks/ValidationHook.php';
require_once __DIR__ . '/Examples/Hooks/LoggingHook.php';
require_once __DIR__ . '/Examples/Hooks/CalculationHook.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test hook mocking functionality
 * 
 * This test class covers hook mocking features:
 * - Simulating critical hook failures
 * - Simulating optional hook failures (should continue execution)
 * - Custom behavior mocking for specific test scenarios
 */
class HookMockingTest extends TestCase
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
     * Test simulating critical hook failure
     * 
     * Critical hook failures should stop execution and throw exceptions.
     */
    public function test_mock_critical_hook_failure(): void
    {
        $this->mockHook(ValidationHook::class)
            ->shouldFail(new \Exception('Validation failed'));

        $this->expectException(HookExecutionException::class);
        $this->expectExceptionMessage('Validation failed');

        $service = new ExampleService();
        $service->processData(['item1', 'item2']);
    }

    /**
     * Test simulating optional hook failure
     * 
     * Optional hook failures should not stop execution, ensuring system resilience.
     */
    public function test_mock_optional_hook_failure_continues(): void
    {
        $this->mockHook(LoggingHook::class)
            ->shouldFail(new \Exception('Logging service down'));

        $service = new ExampleService();
        $result = $service->processData(['item1', 'item2']);

        $this->assertNotNull($result);
        $this->assertTrue($result->processed);
    }

    /**
     * Test mock with custom behavior
     * 
     * Allows simulating specific test scenarios with custom logic.
     */
    public function test_mock_hook_with_custom_behavior(): void
    {
        $customTax = 1.25;

        $this->mockHook(CalculationHook::class)
            ->with(function(&$args) use ($customTax) {
                $args['result'] = $args['amount'] * $customTax;
            });

        $service = new ExampleService();
        $result = $service->calculateAmount(100.00);

        $this->assertEquals(100.00, $result->original);
        $this->assertEquals(125.00, $result->calculated);
    }
}
