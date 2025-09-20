<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\DataTransformHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\LoggingHook;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/Examples/Hooks/DataTransformHook.php';
require_once __DIR__ . '/Examples/Hooks/LoggingHook.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test hook replacement functionality
 * 
 * This test class covers hook replacement features:
 * - Replacing hooks with simple closures
 * - Replacing multiple hooks for controlled test scenarios
 */
class HookReplacementTest extends TestCase
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
     * Test replacing hook with simple closure
     * 
     * Useful for simplifying complex behavior in tests.
     */
    public function test_replace_hook_with_closure(): void
    {
        $executionCount = 0;

        $this->replaceHook(DataTransformHook::class)
            ->with(function(&$args) use (&$executionCount) {
                $executionCount++;
                $args['result']['custom_field'] = 'replaced_value';
            });

        $service = new ExampleService();
        $result = $service->processData(['item1']);

        $this->assertEquals('replaced_value', $result->custom_field);
        $this->assertEquals(1, $executionCount);
    }

    /**
     * Test replacing multiple hooks for test scenarios
     * 
     * Useful for creating controlled test environments.
     */
    public function test_replace_multiple_hooks_for_scenario(): void
    {
        $this->replaceHook(LoggingHook::class)
            ->with(function(&$args) {
                $args['options']['test_mode'] = true;
            });

        $this->replaceHook(DataTransformHook::class)
            ->with(function(&$args) {
                $args['result']['test_flag'] = 'mocked';
            });

        $service = new ExampleService();
        $result = $service->processData(['item1'], []);

        $this->assertEquals('mocked', $result->test_flag);
    }
}
