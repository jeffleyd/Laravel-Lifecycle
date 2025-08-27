<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;

/**
 * Mutable hooks tests - Pass by reference functionality
 */
class MutableHooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->manager->setHooksFor(OrderService::class, collect());
        $this->manager->setHooksFor(PaymentProcessingService::class, collect());
    }
    
    /** @test */
    public function it_allows_hooks_to_modify_values_by_reference(): void
    {
        $hook = new ApplyDiscountHook();
        addHook(PaymentProcessingService::class, $hook);
        
        $userId = 123;
        $amount = 100.00;
        $originalAmount = $amount;
        

        runHook(PaymentProcessingService::class, 'before_payment', $userId, $amount);

        $this->assertEquals(123, $userId);
        $this->assertEquals(90.00, $amount);
        $this->assertNotEquals($originalAmount, $amount);
    }
    
    /** @test */
    public function it_supports_multiple_hooks_chaining_modifications(): void
    {
        addHook(PaymentProcessingService::class, new ApplyDiscountHook());
        addHook(PaymentProcessingService::class, new ApplyTaxHook());
        
        $userId = 123;
        $amount = 100.00;
        
        runHook(PaymentProcessingService::class, 'before_payment', $userId, $amount);
        
        $this->assertEquals(97.20, $amount);
        $this->assertEquals(123, $userId);
    }
    
    /** @test */
    public function it_demonstrates_real_world_payment_flow_with_mutations(): void
    {
        // Set up hooks for payment processing
        addHook(PaymentProcessingService::class, new ValidateAmountHook());
        addHook(PaymentProcessingService::class, new ApplyDiscountHook());
        addHook(PaymentProcessingService::class, new ApplyTaxHook());
        
        $service = new PaymentProcessingService();
        $result = $service->processPayment(123, 100.00);
        
        // Verify the payment flow worked correctly with mutations
        $this->assertEquals(100.00, $result['original_amount']);
        $this->assertEquals(97.20, $result['final_amount']); // After discount and tax
        $this->assertEquals(123, $result['user_id']);
        $this->assertArrayHasKey('payment_id', $result);
    }
    
    /** @test */
    public function it_supports_calculate_total_with_spread_operator(): void
    {
        // Based on README example with spread operator
        addHook(OrderService::class, new CalculateTaxHook());
        addHook(OrderService::class, new ApplyDiscountCalculationHook());
        
        $service = new OrderService();
        $result = $service->calculateTotal(['item1', 'item2']);
        
        $this->assertArrayHasKey('subtotal', $result);
        $this->assertArrayHasKey('tax', $result);
        $this->assertArrayHasKey('discount', $result);
        $this->assertArrayHasKey('total', $result);
        
        // Verify calculations were modified by hooks
        $this->assertGreaterThan(0, $result['tax']);
        $this->assertGreaterThan(0, $result['discount']);
        $this->assertEquals(
            $result['subtotal'] + $result['tax'] - $result['discount'], 
            $result['total']
        );
    }
    
    /** @test */
    public function it_preserves_original_values_when_no_hooks_modify_them(): void
    {
        // Hook that doesn't modify values
        addHook(PaymentProcessingService::class, new LoggingHook());
        
        $userId = 123;
        $amount = 100.00;
        $originalUserId = $userId;
        $originalAmount = $amount;
        
        runHook(PaymentProcessingService::class, 'before_payment', $userId, $amount);
        
        $this->assertEquals($originalUserId, $userId);
        $this->assertEquals($originalAmount, $amount);
    }
    
    /** @test */
    public function it_handles_conditional_modifications(): void
    {
        // Test with high-value transaction (should get discount)
        $this->manager->setHooksFor(PaymentProcessingService::class, collect());
        addHook(PaymentProcessingService::class, new ConditionalDiscountHook());
        
        $userId = 123;
        $highAmount = 1001.00; // Maior que 1000 para ativar a condição
        
        runHook(PaymentProcessingService::class, 'before_payment', $userId, $highAmount);
        
        $this->assertEquals(900.90, $highAmount); // 10% discount applied (1001 * 0.9)
        
        // Reset hooks for second test
        $this->manager->setHooksFor(PaymentProcessingService::class, collect());
        addHook(PaymentProcessingService::class, new ConditionalDiscountHook());
        
        // Test with low-value transaction (should not get discount)
        $lowAmount = 50.00;
        
        runHook(PaymentProcessingService::class, 'before_payment', $userId, $lowAmount);
        
        $this->assertEquals(50.00, $lowAmount); // No discount applied
    }
}


#[LifeCyclePoint('before_payment', ['user_id', 'amount'])]
#[LifeCyclePoint('after_payment', ['user_id', 'amount', 'payment_id'])]
class PaymentProcessingService
{
    use HasLifecycle;
    
    public function processPayment(int $userId, float $amount): array
    {
        $originalAmount = $amount;
        

        runHook($this, 'before_payment', $userId, $amount);
        
        // Use modified values directly (README example)
        $paymentId = 'PAY_' . uniqid();
        
        runHook($this, 'after_payment', $userId, $amount, $paymentId);
        
        return [
            'original_amount' => $originalAmount,
            'final_amount' => $amount,  // Modified directly by hooks (README example)
            'user_id' => $userId,
            'payment_id' => $paymentId,
        ];
    }
}


#[LifeCyclePoint('calculate_total', ['items', 'subtotal', 'tax', 'discount'])]
class OrderService
{
    use HasLifecycle;
    
    public function calculateTotal(array $items): array
    {
        $subtotal = $this->calculateSubtotal($items);
        $tax = 0;
        $discount = 0;
        

        runHook($this, 'calculate_total', $items, $subtotal, $tax, $discount);
        
        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $subtotal + $tax - $discount
        ];
    }
    
    private function calculateSubtotal(array $items): float
    {
        return count($items) * 50.0; // $50 per item
    }
}


#[Hook(scope: 'PaymentProcessingService', point: 'before_payment', severity: Severity::Optional)]
class ApplyDiscountHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args['amount'] *= 0.9;
    }
}


#[Hook(scope: 'PaymentProcessingService', point: 'before_payment', severity: Severity::Optional)]
class ApplyTaxHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args['amount'] *= 1.08;
    }
}


#[Hook(scope: 'PaymentProcessingService', point: 'before_payment', severity: Severity::Critical)]
class ValidateAmountHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        if ($args['amount'] <= 0) {
            throw new \Exception('Invalid payment amount');
        }
    }
}


#[Hook(scope: 'OrderService', point: 'calculate_total', severity: Severity::Optional)]
class CalculateTaxHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args['tax'] = $args['subtotal'] * 0.10;
    }
}


#[Hook(scope: 'OrderService', point: 'calculate_total', severity: Severity::Optional)]
class ApplyDiscountCalculationHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args['discount'] = $args['subtotal'] * 0.05;
    }
}


#[Hook(scope: 'PaymentProcessingService', point: 'before_payment', severity: Severity::Optional)]
class LoggingHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {

    }
}


#[Hook(scope: 'PaymentProcessingService', point: 'before_payment', severity: Severity::Optional)]
class ConditionalDiscountHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        if ($args['amount'] > 1000) {
            $args['amount'] *= 0.9;
        }
    }
}