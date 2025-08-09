<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

class MutableHooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->manager->setHooksFor(MutableService::class, collect());
    }
    
    public function test_hook_can_modify_values_passed_by_reference(): void
    {
        $hook = new ModifyingHook();
        addHook(MutableService::class, $hook);
        
        $value1 = 'original';
        $value2 = 100;
        
        runHook(MutableService::class, 'process', $value1, $value2);

        $this->assertEquals('modified', $value1);
        $this->assertEquals(200, $value2);
    }
    
    public function test_hook_modifies_values_by_reference(): void
    {
        addHook(MutableService::class, new DiscountHook());
        
        $userId = 123;
        $amount = 100.00;
        $discount = 0.00;
        
        runHook(MutableService::class, 'apply_discount', $userId, $amount, $discount);

        $this->assertEquals(123, $userId);
        $this->assertEquals(100.00, $amount);
        $this->assertEquals(10.00, $discount);
    }
    
    public function test_multiple_hooks_can_chain_modifications(): void
    {
        addHook(MutableService::class, new DiscountHook());
        addHook(MutableService::class, new TaxHook());
        addHook(MutableService::class, new RoundingHook());
        
        $userId = 123;
        $amount = 100.00;
        $discount = 0.00;
        
        runHook(MutableService::class, 'apply_discount', $userId, $amount, $discount);

        $this->assertEquals(10.00, $discount);
        $this->assertEquals(95.00, $amount);
    }
    
    public function test_real_world_payment_flow_with_mutations(): void
    {
        $paymentService = new PaymentService();

        addHook(PaymentService::class, new ValidatePaymentHook());
        addHook(PaymentService::class, new ApplyDiscountHook());
        addHook(PaymentService::class, new CalculateTaxHook());
        
        $result = $paymentService->processPayment(100.00, 'USD');
        
        $this->assertEquals(100.00, $result['original_amount']);
        $this->assertEquals(97.20, $result['final_amount']);
        $this->assertEquals('USD', $result['currency']);
    }
}

class MutableService implements LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'process' => ['value1', 'value2'],
            'apply_discount' => ['userId', 'amount', 'discount'],
        ];
    }
}

class PaymentService implements LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'before_payment' => ['amount', 'currency'],
            'after_payment' => ['transactionId', 'amount'],
        ];
    }
    
    public function processPayment(float $amount, string $currency): array
    {
        $originalAmount = $amount;
        
        runHook($this, 'before_payment', $amount, $currency);
        
        // Simulate payment processing
        $transactionId = 'TXN-' . uniqid();
        
        runHook($this, 'after_payment', $transactionId, $amount);
        
        return [
            'original_amount' => $originalAmount,
            'final_amount' => $amount,
            'currency' => $currency,
            'transaction_id' => $transactionId,
        ];
    }
}

class ModifyingHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'process';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $args['value1'] = 'modified';
        $args['value2'] = $args['value2'] * 2;
    }
}

class DiscountHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'apply_discount';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        // Apply 10% discount
        $args['discount'] = $args['amount'] * 0.10;
    }
}

class TaxHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'apply_discount';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $netAmount = $args['amount'] - $args['discount'];
        $tax = $netAmount * 0.08;
        $args['amount'] = $netAmount + $tax;
    }
}

class RoundingHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'apply_discount';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $args['amount'] = round($args['amount'] / 5) * 5;
    }
}

class ValidatePaymentHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'before_payment';
    }
    
    public function getSeverity(): string
    {
        return 'critical';
    }
    
    public function handle(array &$args): void
    {
        if ($args['amount'] <= 0) {
            throw new \Exception('Invalid payment amount');
        }
    }
}

class ApplyDiscountHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'before_payment';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $args['amount'] = $args['amount'] * 0.90;
    }
}

class CalculateTaxHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'before_payment';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $args['amount'] = $args['amount'] * 1.08;
    }
}