<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test hook debugging functionality
 * 
 * This test class covers debugging features:
 * - Full execution trace debugging
 * - Hook execution path logging
 */
class HookDebuggingTest extends TestCase
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
     * Test debug hook execution with full trace
     * 
     * Useful for diagnosing complex problems and understanding execution flow.
     */
    public function test_debug_hook_execution_with_full_trace(): void
    {
        $service = new ExampleService();
        $service->processData(['item1', 'item2', 'item3']);

        $trace = $this->getHookTrace();

        $this->assertGreaterThanOrEqual(2, count($trace), 'Expected hooks to execute');

        foreach ($trace as $entry) {
            $this->assertArrayHasKey('hook', $entry);
            $this->assertArrayHasKey('lifecycle', $entry);
            $this->assertArrayHasKey('class', $entry);
        }

        $hookNames = array_column($trace, 'hook');
        $this->assertNotEmpty($hookNames);
        $this->assertNotEmpty($trace);
    }
}
