<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;

/**
 * Helper functions tests - Global functions functionality
 */
class HelperFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->manager->setHooksFor(ControllerTestService::class, collect());
    }
    
    /** @test */
    public function runHook_executes_hooks_from_controller_context(): void
    {
        $hook = new ControllerTestHook();
        addHook(ControllerTestService::class, $hook);
        
        $amount = 100.00;
        runHook(ControllerTestService::class, 'payment.begin', $amount);
        
        $this->assertTrue($hook->wasExecuted());
        $this->assertEquals(['amount' => 100.00], $hook->getReceivedArgs());
    }
    
    /** @test */
    public function runHook_works_with_class_name_and_instance(): void
    {
        $hook = new ControllerTestHook();
        
        addHook(ControllerTestService::class, $hook);
        $amount = 100.00;
        runHook(ControllerTestService::class, 'payment.begin', $amount);
        $this->assertTrue($hook->wasExecuted());
        
        $hook->reset();
        
        $service = new ControllerTestService();
        $amount = 200.00;
        runHook($service, 'payment.begin', $amount);
        $this->assertTrue($hook->wasExecuted());
        $this->assertEquals(['amount' => 200.00], $hook->getReceivedArgs());
    }
    
    /** @test */
    public function runHook_supports_external_execution_from_tests(): void
    {
        $amount = 100.00;
        
        $testHook = new TestPaymentHook();
        addHook(ControllerTestService::class, $testHook);
        runHook(ControllerTestService::class, 'payment.begin', $amount);
        
        $this->assertTrue($testHook->wasExecuted());
    }
    
    /** @test */
    public function addHook_adds_hooks_dynamically(): void
    {
        $customHook = new MyCustomHook();
        addHook(ControllerTestService::class, $customHook);
        
        $hooks = $this->manager->getHooksFor(ControllerTestService::class);
        $this->assertCount(1, $hooks);
        
        $amount = 100.00;
        runHook(ControllerTestService::class, 'payment.begin', $amount);
        $this->assertTrue($customHook->wasExecuted());
    }
    
    /** @test */
    public function addHook_works_with_both_class_name_and_instance(): void
    {
        $hook1 = new ControllerTestHook();
        $hook2 = new TestPaymentHook();
        $service = new ControllerTestService();
        
        addHook(ControllerTestService::class, $hook1);
        
        addHook($service, $hook2);
        
        $hooks = $this->manager->getHooksFor(ControllerTestService::class);
        $this->assertCount(2, $hooks);
    }
    
    /** @test */
    public function removeHooksFor_removes_hooks_for_specific_lifecycle(): void
    {
        $hook1 = new ControllerTestHook();
        $hook2 = new HelperOtherLifecycleHook();
        
        addHook(ControllerTestService::class, $hook1);
        addHook(ControllerTestService::class, $hook2);
        
        $this->assertCount(2, $this->manager->getHooksFor(ControllerTestService::class));
        
        removeHooksFor(ControllerTestService::class, 'payment.begin');
        
        $remainingHooks = $this->manager->getHooksFor(ControllerTestService::class);
        $this->assertCount(1, $remainingHooks);
        
        $remainingHook = $remainingHooks->first();
        $this->assertEquals('other.lifecycle', $remainingHook->getLifeCycle());
    }
    
    /** @test */
    public function removeHooksFor_works_with_class_name_and_instance(): void
    {
        $hook = new ControllerTestHook();
        $service = new ControllerTestService();
        
        addHook($service, $hook);
        $this->assertCount(1, $this->manager->getHooksFor(ControllerTestService::class));
        
        removeHooksFor(ControllerTestService::class, 'payment.begin');
        $this->assertCount(0, $this->manager->getHooksFor(ControllerTestService::class));
        
        addHook($service, $hook);
        removeHooksFor($service, 'payment.begin');
        $this->assertCount(0, $this->manager->getHooksFor(ControllerTestService::class));
    }
    
    /** @test */
    public function helper_functions_support_mutable_hooks(): void
    {
        $hook = new MutableHelperHook();
        addHook(ControllerTestService::class, $hook);
        
        $amount = 100.00;
        $originalAmount = $amount;
        
        runHook(ControllerTestService::class, 'payment.begin', $amount);
        
        $this->assertNotEquals($originalAmount, $amount);
        $this->assertEquals(90.00, $amount); // 10% discount applied
    }
    
    /** @test */
    public function helper_functions_work_in_controller_simulation(): void
    {
        $controller = new SimulatedPaymentController();
        $result = $controller->process(100.00, 'USD', 123);
        
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('hooks_executed', $result);
        $this->assertTrue($result['hooks_executed']);
    }
}


#[LifeCyclePoint('payment.begin', ['amount'])]
#[LifeCyclePoint('other.lifecycle', ['data'])]
class ControllerTestService
{
    use HasLifecycle;
}


#[Hook(scope: 'ControllerTestService', point: 'payment.begin', severity: Severity::Optional)]
class ControllerTestHook
{
    use Hookable;
    
    private bool $executed = false;
    private array $receivedArgs = [];
    
    public function handle(array &$args): void
    {
        $this->executed = true;
        $this->receivedArgs = $args;
    }
    
    public function wasExecuted(): bool
    {
        return $this->executed;
    }
    
    public function getReceivedArgs(): array
    {
        return $this->receivedArgs;
    }
    
    public function reset(): void
    {
        $this->executed = false;
        $this->receivedArgs = [];
    }
}


#[Hook(scope: 'ControllerTestService', point: 'payment.begin', severity: Severity::Optional)]
class TestPaymentHook
{
    use Hookable;
    
    private bool $executed = false;
    
    public function handle(array &$args): void
    {
        $this->executed = true;
    }
    
    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}


#[Hook(scope: 'ControllerTestService', point: 'payment.begin', severity: Severity::Optional)]
class MyCustomHook
{
    use Hookable;
    
    private bool $executed = false;
    
    public function handle(array &$args): void
    {
        $this->executed = true;
    }
    
    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}


#[Hook(scope: 'ControllerTestService', point: 'other.lifecycle', severity: Severity::Optional)]
class HelperOtherLifecycleHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
    }
}


#[Hook(scope: 'ControllerTestService', point: 'payment.begin', severity: Severity::Optional)]
class MutableHelperHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args['amount'] *= 0.9;
    }
}


class SimulatedPaymentController
{
    public function process(float $amount, string $currency, int $userId): array
    {
        addHook(ControllerTestService::class, new TestPaymentHook());
        
        runHook(ControllerTestService::class, 'payment.begin', $amount);
        
        $service = new ControllerTestService();
        
        return [
            'processed' => true,
            'hooks_executed' => true,
            'amount' => $amount,
            'currency' => $currency,
            'user_id' => $userId,
        ];
    }
}
