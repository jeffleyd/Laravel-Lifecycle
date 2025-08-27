<?php

namespace App\Services;

use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;

#[LifeCyclePoint('before_payment', ['user_id', 'amount'])]
#[LifeCyclePoint('after_payment', ['user_id', 'amount', 'payment_id'])]
#[LifeCyclePoint('payment_failed', ['user_id', 'amount', 'error'])]
class PaymentService
{
    use HasLifecycle;
    
    public function processPayment(int $userId, float $amount): array
    {
        // Executar hooks antes do pagamento - FUNCIONA TANTO DENTRO QUANTO FORA DA CLASSE!
        runHook($this, 'before_payment', $userId, $amount);
        
        try {
            // Simular processamento do pagamento
            $paymentId = $this->executePayment($userId, $amount);
            
            // Executar hooks após pagamento bem-sucedido
            runHook($this, 'after_payment', $userId, $amount, $paymentId);
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'message' => 'Pagamento processado com sucesso'
            ];
            
        } catch (\Exception $e) {
            // Executar hooks em caso de falha
            runHook($this, 'payment_failed', $userId, $amount, $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Método alternativo usando a trait (opcional)
    public function processPaymentWithTrait(int $userId, float $amount): array
    {
        // Também funciona usando o método da trait
        $this->runLifeCycleHook('before_payment', $userId, $amount);
        
        try {
            $paymentId = $this->executePayment($userId, $amount);
            $this->runLifeCycleHook('after_payment', $userId, $amount, $paymentId);
            
            return ['success' => true, 'payment_id' => $paymentId];
            
        } catch (\Exception $e) {
            $this->runLifeCycleHook('payment_failed', $userId, $amount, $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function executePayment(int $userId, float $amount): string
    {
        // Simular lógica de pagamento
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Valor deve ser maior que zero');
        }
        
        // Simular falha ocasional para demonstrar hooks de erro
        if (rand(1, 10) === 1) {
            throw new \Exception('Falha na comunicação com gateway de pagamento');
        }
        
        return 'PAY_' . uniqid();
    }
}
