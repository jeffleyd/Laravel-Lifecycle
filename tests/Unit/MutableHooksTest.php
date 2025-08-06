<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use PhpDiffused\Lifecycle\Support\HasLifeCycleHooks;

class MutableHooksTest extends TestCase
{
    private PaymentTestService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentTestService();
    }
    
    public function test_hook_can_modify_values_passed_by_reference(): void
    {
        $userId = 123;
        $amount = 100.00;
        $coupon = null;
        $fees = 0.00;
        
        $discountHook = new ApplyDiscountHook();
        $this->service->addHook($discountHook);
        
        $this->service->runHook('before_payment', $userId, $amount, $coupon, $fees);
        
        $this->assertEquals(90.00, $amount, 'Amount should be modified by discount');
        $this->assertEquals('DISCOUNT10', $coupon, 'Coupon should be set by hook');
        $this->assertEquals(123, $userId, 'User ID should remain unchanged');
    }
    
    public function test_hook_modifies_values_by_reference(): void
    {
        // Arrange
        $userId = 123;
        $amount = 100.00;
        $coupon = null;
        $fees = 0.00;
        $modifierHook = new ModifierHook();
        $this->service->addHook($modifierHook);
        
        // Act - values are passed by reference
        $this->service->runHook('before_payment', $userId, $amount, $coupon, $fees);
        
        $this->assertEquals(999.99, $amount, 'Amount should be modified by hook');
        $this->assertEquals(123, $userId, 'User ID should remain unchanged');
    }
    
    public function test_multiple_hooks_can_chain_modifications(): void
    {
        $userId = 123;
        $amount = 100.00;
        $coupon = null;
        $fees = 0.00;
        
        $discountHook = new ApplyDiscountHook();      // -10%
        $taxHook = new ApplyTaxHook();               // +8%
        $feeHook = new ProcessingFeeHook();          // +$2.50
        
        $this->service->addHook($discountHook);
        $this->service->addHook($taxHook);
        $this->service->addHook($feeHook);
        
        $this->service->runHook('before_payment', $userId, $amount, $coupon, $fees);
        
        $this->assertEquals(97.20, $amount, 'Amount modified by discount then tax');
        $this->assertEquals(2.50, $fees, 'Processing fee should be added');
    }
    
    public function test_real_world_payment_flow_with_mutations(): void
    {
        $service = new CompletePaymentService();
        
        $service->addHook(new ValidateAmountHook());     // Security team
        $service->addHook(new ApplyLoyaltyDiscountHook()); // Marketing team
        $service->addHook(new ConvertCurrencyHook());    // Finance team
        
        $result = $service->processPayment(123, 100.00, 'USD');
        
        $this->assertEquals(81.00, $result['final_amount']); // 100 -> 90 (loyalty) -> 81 (BRL conversion)
        $this->assertEquals('BRL', $result['currency']);
        $this->assertTrue($result['loyalty_applied']);
    }
}

// Test Service
class PaymentTestService implements LifeCycle
{
    use HasLifeCycleHooks;
    
    public static function lifeCycle(): array
    {
        return [
            'before_payment' => ['user_id', 'amount', 'coupon', 'fees'],
            'after_payment' => ['payment_id', 'status'],
        ];
    }
}

class ApplyDiscountHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before_payment'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        if (isset($args['amount'])) {
            $args['amount'] *= 0.9; // 10% discount
        }
        if (array_key_exists('coupon', $args)) {
            $args['coupon'] = 'DISCOUNT10';
        }
    }
}

class ModifierHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before_payment'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        if (isset($args['amount'])) {
            $args['amount'] = 999.99;
        }
    }
}

class ApplyTaxHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before_payment'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        if (isset($args['amount'])) {
            $args['amount'] *= 1.08; // 8% tax
        }
    }
}

class ProcessingFeeHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before_payment'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        if (isset($args['fees'])) {
            $args['fees'] += 2.50;
        }
    }
}

class CompletePaymentService implements LifeCycle
{
    use HasLifeCycleHooks;
    
    public static function lifeCycle(): array
    {
        return [
            'before_payment' => ['user_id', 'amount', 'currency'],
        ];
    }
    
    public function processPayment(int $userId, float $amount, string $currency): array
    {
        $originalAmount = $amount;
        $originalCurrency = $currency;
        
        $this->runHook('before_payment', $userId, $amount, $currency);
        
        return [
            'user_id' => $userId,
            'original_amount' => $originalAmount,
            'final_amount' => $amount,
            'currency' => $currency,
            'loyalty_applied' => $currency !== $originalCurrency || $amount !== $originalAmount
        ];
    }
}

class ValidateAmountHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before_payment'; }
    public function getSeverity(): string { return 'critical'; }
    
    public function handle(array &$args): void
    {
        if ($args['amount'] <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
        // Poderia ajustar para valor mínimo
        if ($args['amount'] < 1.00) {
            $args['amount'] = 1.00;
        }
    }
}

class ApplyLoyaltyDiscountHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before_payment'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        if ($args['user_id'] == 123) { // Cliente VIP
            $args['amount'] *= 0.9; // 10% desconto
            $args['loyalty_applied'] = true;
        }
    }
}

class ConvertCurrencyHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before_payment'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        if ($args['currency'] === 'USD') {
            $args['amount'] *= 0.9; // Simula conversão USD -> BRL
            $args['currency'] = 'BRL';
        }
    }
}