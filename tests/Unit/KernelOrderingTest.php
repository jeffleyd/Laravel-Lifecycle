<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;

/**
 * Kernel ordering tests - Hook execution order control
 */
class KernelOrderingTest extends TestCase
{
    protected static array $executionOrder = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        self::$executionOrder = [];
        $this->manager->setHooksFor(OrderedPaymentService::class, collect());
        $this->manager->setHooksKernel(new TestKernel());
    }
    
    /** @test */
    public function it_executes_hooks_in_kernel_defined_order(): void
    {
        addHook(OrderedPaymentService::class, new ApplyDiscountOrderedHook());
        addHook(OrderedPaymentService::class, new ValidateAmountOrderedHook());
        addHook(OrderedPaymentService::class, new FraudDetectionOrderedHook());
        
        $userId = 123;
        $amount = 100.00;
        
        runHook(OrderedPaymentService::class, 'before_payment', $userId, $amount);
        
        $this->assertEquals(['validate', 'fraud', 'discount'], self::$executionOrder);
    }
    
    /** @test */
    public function it_executes_different_lifecycles_with_different_orders(): void
    {
        $userId = 123;
        $amount = 100.00;
        $paymentId = 'PAY_123';
        
        self::$executionOrder = [];
        runHook(OrderedPaymentService::class, 'before_payment', $userId, $amount);
        $this->assertEquals(['validate', 'fraud', 'discount'], self::$executionOrder);
        
        self::$executionOrder = [];
        runHook(OrderedPaymentService::class, 'after_payment', $userId, $amount, $paymentId);
        $this->assertEquals(['email', 'analytics'], self::$executionOrder);
    }
    
    /** @test */
    public function it_handles_mixed_ordered_and_unordered_hooks(): void
    {
        $userId = 123;
        $amount = 100.00;
        
        runHook(OrderedPaymentService::class, 'before_payment', $userId, $amount);
        
        $this->assertContains('validate', self::$executionOrder);
        $this->assertContains('fraud', self::$executionOrder);
        $this->assertContains('discount', self::$executionOrder);
        
        $validateIndex = array_search('validate', self::$executionOrder);
        $fraudIndex = array_search('fraud', self::$executionOrder);
        $discountIndex = array_search('discount', self::$executionOrder);
        
        $this->assertLessThan($fraudIndex, $validateIndex);
        $this->assertLessThan($discountIndex, $fraudIndex);
    }
    
    /** @test */
    public function it_works_without_kernel_configuration(): void
    {
                $this->manager->setHooksKernel(new EmptyKernel());
        
        addHook(OrderedPaymentService::class, new ValidateAmountOrderedHook());
        addHook(OrderedPaymentService::class, new FraudDetectionOrderedHook());
        
        $userId = 123;
        $amount = 100.00;
        
        runHook(OrderedPaymentService::class, 'before_payment', $userId, $amount);
        
                $this->assertEquals(['validate', 'fraud'], self::$executionOrder);
    }
    
    /** @test */
    public function it_handles_kernel_with_non_existent_hooks(): void
    {
                $this->manager->setHooksKernel(new KernelWithNonExistentHooks());
        
                addHook(OrderedPaymentService::class, new ValidateAmountOrderedHook());
        
        $userId = 123;
        $amount = 100.00;
        
                runHook(OrderedPaymentService::class, 'before_payment', $userId, $amount);
        
        $this->assertEquals(['validate'], self::$executionOrder);
    }
    
    /** @test */
    public function it_demonstrates_readme_example_ordering(): void
    {
                $this->manager->setHooksKernel(new ReadmeExampleKernel());
        
        addHook(OrderedPaymentService::class, new ValidateAmountOrderedHook());    // 1st: Validation
        addHook(OrderedPaymentService::class, new FraudDetectionOrderedHook());   // 2nd: Security  
        addHook(OrderedPaymentService::class, new ApplyDiscountOrderedHook());    // 3rd: Business logic
        
        $userId = 123;
        $amount = 100.00;
        
        runHook(OrderedPaymentService::class, 'before_payment', $userId, $amount);
        
                $this->assertEquals(['validate', 'fraud', 'discount'], self::$executionOrder);
    }
    
    public static function recordExecution(string $hookName): void
    {
        self::$executionOrder[] = $hookName;
    }
}

// =================== TEST SERVICE CLASS ===================

/**
 * Modern service for testing kernel ordering with PHP 8+ attributes (based on README example)
 */
#[LifeCyclePoint('before_payment', ['user_id', 'amount'])]
#[LifeCyclePoint('after_payment', ['user_id', 'amount', 'payment_id'])]
class OrderedPaymentService
{
    use HasLifecycle;
}

// =================== TEST KERNEL CLASSES ===================

/**
 * Test kernel based on README example
 */
class TestKernel
{
    public array $hooks = [
        OrderedPaymentService::class => [
            'before_payment' => [
                ValidateAmountOrderedHook::class,    // 1st
                FraudDetectionOrderedHook::class,    // 2nd  
                ApplyDiscountOrderedHook::class,     // 3rd
            ],
            'after_payment' => [
                SendEmailOrderedHook::class,         // 1st
                UpdateAnalyticsOrderedHook::class,   // 2nd
            ]
        ],
    ];
}

/**
 * Empty kernel for testing without configuration
 */
class EmptyKernel
{
    public array $hooks = [];
}

/**
 * Kernel with non-existent hooks for error handling test
 */
class KernelWithNonExistentHooks
{
    public array $hooks = [
        OrderedPaymentService::class => [
            'before_payment' => [
                ValidateAmountOrderedHook::class,
                'NonExistentHook',                  'AnotherNonExistentHook',
            ],
        ],
    ];
}

/**
 * Kernel based on README example
 */
class ReadmeExampleKernel
{
    public array $hooks = [
        OrderedPaymentService::class => [
            'before_payment' => [
                ValidateAmountOrderedHook::class,    // 1st: Validation
                FraudDetectionOrderedHook::class,    // 2nd: Security
                ApplyDiscountOrderedHook::class,     // 3rd: Business logic
            ]
        ],
    ];
}

// =================== TEST HOOK CLASSES ===================

abstract class OrderedHook
{
    use Hookable;
    
    protected string $name;
    
    public function handle(array &$args): void
    {
        KernelOrderingTest::recordExecution($this->name);
    }
}

#[Hook(scope: 'OrderedPaymentService', point: 'before_payment', severity: Severity::Optional)]
class ValidateAmountOrderedHook extends OrderedHook
{
    protected string $name = 'validate';
}

#[Hook(scope: 'OrderedPaymentService', point: 'before_payment', severity: Severity::Optional)]
class FraudDetectionOrderedHook extends OrderedHook
{
    protected string $name = 'fraud';
}

#[Hook(scope: 'OrderedPaymentService', point: 'before_payment', severity: Severity::Optional)]
class ApplyDiscountOrderedHook extends OrderedHook
{
    protected string $name = 'discount';
}

#[Hook(scope: 'OrderedPaymentService', point: 'after_payment', severity: Severity::Optional)]
class SendEmailOrderedHook extends OrderedHook
{
    protected string $name = 'email';
}

#[Hook(scope: 'OrderedPaymentService', point: 'after_payment', severity: Severity::Optional)]
class UpdateAnalyticsOrderedHook extends OrderedHook
{
    protected string $name = 'analytics';
}

#[Hook(scope: 'OrderedPaymentService', point: 'before_payment', severity: Severity::Optional)]
class KernelUnorderedHook extends OrderedHook
{
    protected string $name = 'unordered';
}
