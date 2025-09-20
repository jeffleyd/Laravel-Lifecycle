<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;

/**
 * Core lifecycle functionality tests based on README.md
 */
class CoreLifeCycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->manager->setHooksFor(PaymentService::class, collect());
    }
    
    /** @test */
    public function it_defines_lifecycle_points_correctly(): void
    {
        $lifeCycles = PaymentService::lifeCycle();
        
        $this->assertArrayHasKey('before_payment', $lifeCycles);
        $this->assertArrayHasKey('after_payment', $lifeCycles);
        $this->assertArrayHasKey('payment_failed', $lifeCycles);
        
        $this->assertEquals(['user_id', 'amount'], $lifeCycles['before_payment']);
        $this->assertEquals(['user_id', 'amount', 'payment_id'], $lifeCycles['after_payment']);
        $this->assertEquals(['user_id', 'amount', 'error'], $lifeCycles['payment_failed']);
    }
    
    /** @test */
    public function it_executes_hooks_for_valid_lifecycle(): void
    {
        $hook = new EmailNotificationHook();
        addHook(PaymentService::class, $hook);
        
        $userId = 123;
        $amount = 100.50;
        $paymentId = 'PAY_123';
        
        runHook(PaymentService::class, 'after_payment', $userId, $amount, $paymentId);
        
        $this->assertTrue($hook->wasExecuted());
        $this->assertEquals([
            'user_id' => 123,
            'amount' => 100.50,
            'payment_id' => 'PAY_123'
        ], $hook->getReceivedArgs());
    }
    
    /** @test */
    public function it_executes_hooks_with_service_instance(): void
    {
        $service = new PaymentService();
        $hook = new EmailNotificationHook();
        addHook($service, $hook);
        
        $userId = 123;
        $amount = 100.50;
        $paymentId = 'PAY_123';
        
        runHook($service, 'after_payment', $userId, $amount, $paymentId);
        
        $this->assertTrue($hook->wasExecuted());
    }
    
    /** @test */
    public function it_validates_lifecycle_arguments(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("LifeCycle 'before_payment' expects arguments: amount");
        
        $userId = 123;
        runHook(PaymentService::class, 'before_payment', $userId);     }
    
    /** @test */
    public function it_throws_exception_for_invalid_lifecycle(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("LifeCycle 'invalid_lifecycle' is not defined");
        
        $userId = 123;
        $amount = 100.50;
        runHook(PaymentService::class, 'invalid_lifecycle', $userId, $amount);
    }
    
    /** @test */
    public function it_handles_critical_hook_failures(): void
    {
        $hook = new FraudDetectionHook();
        addHook(PaymentService::class, $hook);
        
        $this->expectException(HookExecutionException::class);
        $this->expectExceptionMessage("Critical hook failed in lifecycle 'before_payment'");
        
        $userId = 999;         $amount = 100.50;
        
        runHook(PaymentService::class, 'before_payment', $userId, $amount);
    }
    
    /** @test */
    public function it_handles_optional_hook_failures_gracefully(): void
    {
        $hook = new FailingOptionalHook();
        addHook(PaymentService::class, $hook);
        
        $userId = 123;
        $amount = 100.50;
        $paymentId = 'PAY_123';
        
                runHook(PaymentService::class, 'after_payment', $userId, $amount, $paymentId);
        
        $this->assertTrue(true);     }
    
    /** @test */
    public function it_filters_hooks_by_lifecycle(): void
    {
        $beforeHook = new FraudDetectionHook();
        $afterHook = new EmailNotificationHook();
        
        addHook(PaymentService::class, $beforeHook);
        addHook(PaymentService::class, $afterHook);
        
        $userId = 123;
        $amount = 100.50;
        $paymentId = 'PAY_123';
        
                runHook(PaymentService::class, 'after_payment', $userId, $amount, $paymentId);
        
                $this->assertTrue($afterHook->wasExecuted());
        $this->assertFalse($beforeHook->wasExecuted());
    }
    
    /** @test */
    public function it_supports_external_hook_execution_from_controller(): void
    {
                $hook = new FraudDetectionHook();
        addHook(PaymentService::class, $hook);
        
        $userId = 123;
        $amount = 100.50;
        
                runHook(PaymentService::class, 'before_payment', $userId, $amount);
        
        $this->assertTrue($hook->wasExecuted());
    }
    
    /** @test */
    public function it_supports_dynamic_hook_management(): void
    {
        $hook1 = new EmailNotificationHook();
        $hook2 = new AnalyticsHook();
        
                addHook(PaymentService::class, $hook1);
        addHook(PaymentService::class, $hook2);
        
        $this->assertCount(2, $this->manager->getHooksFor(PaymentService::class));
        
                removeHooksFor(PaymentService::class, 'after_payment');
        
        $this->assertCount(0, $this->manager->getHooksFor(PaymentService::class));
    }
}

#[LifeCyclePoint('before_payment', ['user_id', 'amount'])]
#[LifeCyclePoint('after_payment', ['user_id', 'amount', 'payment_id'])]
#[LifeCyclePoint('payment_failed', ['user_id', 'amount', 'error'])]
class PaymentService
{
    use HasLifecycle;
    
    public function process(int $userId, float $amount): string
    {
        runHook($this, 'before_payment', $userId, $amount);
        
                $paymentId = $this->doPayment($userId, $amount);
        
        runHook($this, 'after_payment', $userId, $amount, $paymentId);
        
        return $paymentId;
    }
    
    private function doPayment(int $userId, float $amount): string
    {
        return 'PAY_' . uniqid();
    }
}

#[Hook(scope: 'PaymentService', point: 'after_payment', severity: Severity::Optional)]
class EmailNotificationHook
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
}

#[Hook(scope: 'PaymentService', point: 'before_payment', severity: Severity::Critical)]
class FraudDetectionHook
{
    use Hookable;
    
    private bool $executed = false;
    
    public function handle(array &$args): void
    {
        $this->executed = true;
        
        if ($this->isFraudulent($args)) {
            throw new \Exception('Suspicious activity detected');
        }
    }
    
    private function isFraudulent(array $args): bool
    {
                return $args['user_id'] === 999;     }
    
    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}

#[Hook(scope: 'PaymentService', point: 'after_payment', severity: Severity::Optional)]
class FailingOptionalHook
{
    use Hookable;

    /**
     * @throws \Exception
     */
    public function handle(array &$args): void
    {
        throw new \Exception('Optional hook failed');
    }
}

#[Hook(scope: 'PaymentService', point: 'after_payment', severity: Severity::Optional)]
class AnalyticsHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
    }
}
