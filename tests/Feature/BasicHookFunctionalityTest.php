<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ValidationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\LoggingHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\DataTransformHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\AuditHook;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/Examples/Hooks/ValidationHook.php';
require_once __DIR__ . '/Examples/Hooks/LoggingHook.php';
require_once __DIR__ . '/Examples/Hooks/DataTransformHook.php';
require_once __DIR__ . '/Examples/Hooks/AuditHook.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test basic hook functionality and isolation
 * 
 * This test class covers the fundamental features of the lifecycle hook system:
 * - Running business logic without hooks
 * - Disabling specific hooks while keeping others active
 * - Filtering hooks by severity level (Critical/Optional)
 */
class BasicHookFunctionalityTest extends TestCase
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
     * Test that business logic works without any hooks
     * 
     * Ensures that the core functionality works independently
     * of the hook system when all hooks are disabled.
     */
    public function test_business_logic_without_any_hooks(): void
    {
        $this->withoutHooks();

        $service = new ExampleService();
        $result = $service->processData(['item1', 'item2']);

        $this->assertNotNull($result);
        $this->assertTrue($result->processed);
        $this->assertEquals(2, $result->items);
        $this->assertEmpty($this->getHookTrace());
    }

    /**
     * Test disabling specific hooks while keeping others active
     * 
     * Useful for isolating problems or testing without specific side effects.
     */
    public function test_disable_specific_hooks_only(): void
    {
        $this->withoutHooks([
            LoggingHook::class,
            DataTransformHook::class
        ]);

        $service = new ExampleService();
        $result = $service->processData(['item1', 'item2']);

        $this->assertExecuted(ValidationHook::class);
        $this->assertExecuted(AuditHook::class);
        $this->assertNotExecuted(LoggingHook::class);
        $this->assertNotExecuted(DataTransformHook::class);
    }

    /**
     * Test executing only critical hooks
     * 
     * Useful for testing minimal essential flow.
     */
    public function test_only_critical_hooks_execution(): void
    {
        $this->onlyHooksWithSeverity(Severity::Critical);

        $service = new ExampleService();
        $result = $service->processData(['item1', 'item2']);

        $this->assertExecuted(ValidationHook::class);
        $this->assertExecuted(AuditHook::class);
        $this->assertNotExecuted(LoggingHook::class);
        $this->assertNotExecuted(DataTransformHook::class);
    }

    /**
     * Test executing only optional hooks
     * 
     * Useful for testing secondary features in isolation.
     */
    public function test_only_optional_hooks_execution(): void
    {
        $this->onlyHooksWithSeverity(Severity::Optional);

        $service = new ExampleService();
        $result = $service->processData(['item1', 'item2']);

        $this->assertExecuted(LoggingHook::class);
        $this->assertExecuted(DataTransformHook::class);
        $this->assertNotExecuted(ValidationHook::class);
        $this->assertNotExecuted(AuditHook::class);
    }
}
