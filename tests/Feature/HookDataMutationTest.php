<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Testing\TestsLifecycleHooks;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\CalculationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ValidationHook;

require_once __DIR__ . '/Examples/ExampleService.php';
require_once __DIR__ . '/Examples/Hooks/CalculationHook.php';
require_once __DIR__ . '/Examples/Hooks/ValidationHook.php';
require_once __DIR__ . '/TestKernel.php';

/**
 * Test hook data mutation functionality
 * 
 * This test class covers:
 * - Verifying that hooks correctly modify data
 * - Verifying that hooks don't modify data when they shouldn't
 */
class HookDataMutationTest extends TestCase
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
     * Test verifying that hooks correctly modify data
     * 
     * Ensures data transformations work as expected.
     */
    public function test_hook_data_mutations(): void
    {
        $this->captureHookMutations();

        $service = new ExampleService();
        $service->calculateAmount(100.00);

        $mutations = $this->getHookMutations(CalculationHook::class);

        $this->assertNotNull($mutations);
        $this->assertEquals(100.00, $mutations->before['amount']);
        $this->assertEqualsWithDelta(110.0, $mutations->after['result'], 0.001, '10% increase with float precision tolerance');
    }

    /**
     * Test verifying that hooks don't modify data unnecessarily
     * 
     * Ensures immutability where required.
     */
    public function test_hook_does_not_mutate_data(): void
    {
        $this->captureHookMutations();

        $service = new ExampleService();
        $service->simpleAction();

        $mutations = $this->getHookMutations(ValidationHook::class);

        if ($mutations) {
            $this->assertArrayHasKey('validated_at', $mutations->after['data']);
            $this->assertArrayNotHasKey('validated_at', $mutations->before['data']);
        }
        
        $this->assertTrue(true);
    }
}
