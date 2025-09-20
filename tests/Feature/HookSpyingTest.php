<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ValidationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\NotificationHook;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/Examples/Hooks/ValidationHook.php';
require_once __DIR__ . '/Examples/Hooks/NotificationHook.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test hook spying functionality
 * 
 * This test class covers the hook spying features:
 * - Verifying that hooks were executed
 * - Counting exact number of executions
 * - Verifying arguments received by hooks
 */
class HookSpyingTest extends TestCase
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
     * Test verifying that a hook was executed
     * 
     * Ensures that important functionality was triggered.
     */
    public function test_spy_hook_execution(): void
    {
        $spy = $this->spyHook(ValidationHook::class);

        $service = new ExampleService();
        $service->processData(['item1', 'item2']);

        $spy->assertExecuted();
    }

    /**
     * Test verifying exact number of executions
     * 
     * Helps avoid duplicate executions or infinite loops.
     */
    public function test_spy_hook_execution_count(): void
    {
        $spy = $this->spyHook(ValidationHook::class);

        $service = new ExampleService();
        $service->processData(['item1']);
        $service->processData(['item2']);
        $service->processData(['item3']);

        $spy->assertExecutedTimes(3);
    }

    /**
     * Test verifying arguments received by hooks
     * 
     * Ensures that correct data is being passed to hooks.
     */
    public function test_spy_hook_received_arguments(): void
    {
        $message = 'Test notification';
        $recipient = 'user@example.com';

        $spy = $this->spyHook(NotificationHook::class);

        $service = new ExampleService();
        $service->sendNotification($message, $recipient);

        $spy->assertReceivedArgs([
            'message' => '[SYSTEM] ' . $message,
            'recipient' => $recipient
        ]);
    }
}
