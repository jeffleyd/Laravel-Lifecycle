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
        $args = [
            'user_id' => 123,
            'amount' => 100.00,
            'coupon' => null
        ];
        
        $discountHook = new ApplyDiscountHook();
        $this->service->addHook($discountHook);
        
        $this->service->runHook('before_payment', $args);
        
        $this->assertEquals(90.00, $args['amount'], 'Amount should be modified by discount');
        $this->assertEquals('DISCOUNT10', $args['coupon'], 'Coupon should be set by hook');
        $this->assertEquals(123, $args['user_id'], 'User ID should remain unchanged');
    }
    
    public function test_hook_cannot_modify_values_not_passed_by_reference(): void
    {
        // Arrange
        $amount = 100.00;
        $modifierHook = new ModifierHook();
        $this->service->addHook($modifierHook);
        
        // Act - criamos uma cópia do array para simular passagem sem referência
        $args = [
            'user_id' => 123,
            'amount' => $amount,
            'coupon' => null
        ];
        $argsCopy = $args; // Cópia do array
        $this->service->runHook('before_payment', $argsCopy);
        
        $this->assertEquals(100.00, $amount, 'Original amount should NOT be modified');
        $this->assertEquals(999.99, $argsCopy['amount'], 'Copy should be modified by hook');
    }
    
    public function test_multiple_hooks_can_chain_modifications(): void
    {
        $args = [
            'user_id' => 123,
            'amount' => 100.00,
            'coupon' => null,
            'fees' => 0.00
        ];
        
        $discountHook = new ApplyDiscountHook();      // -10%
        $taxHook = new ApplyTaxHook();               // +8%
        $feeHook = new ProcessingFeeHook();          // +$2.50
        
        $this->service->addHook($discountHook);
        $this->service->addHook($taxHook);
        $this->service->addHook($feeHook);
        
        $this->service->runHook('before_payment', $args);
        
        $this->assertEquals(97.20, $args['amount'], 'Amount modified by discount then tax');
        $this->assertEquals(2.50, $args['fees'], 'Processing fee should be added');
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
            'before_payment' => ['user_id', 'amount'],  // coupon é opcional
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
        $args['coupon'] = 'DISCOUNT10';
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
        $args = [
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => $currency,
            'loyalty_applied' => false
        ];
        
        $this->runHook('before_payment', $args);
        
        return [
            'user_id' => $userId,
            'original_amount' => $amount,
            'final_amount' => $args['amount'],
            'currency' => $args['currency'],
            'loyalty_applied' => $args['loyalty_applied'] ?? false
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