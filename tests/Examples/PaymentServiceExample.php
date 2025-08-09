<?php

namespace PhpDiffused\Lifecycle\Tests\Examples;

use PhpDiffused\Lifecycle\Contracts\LifeCycle;

/**
 * Exemplo de serviço de pagamento com a nova API
 * Demonstra os ciclos de vida e como organizar hooks em pastas
 */
class PaymentService implements LifeCycle
{
    /**
     * Define os ciclos de vida disponíveis
     * Cada ciclo pode ser organizado em pastas com diferentes formatos:
     * - paymentBegin -> PaymentBegin/ ou payment_begin/ ou payment.begin/
     * - payment_complete -> PaymentComplete/ ou payment.complete/ ou PaymentComplete/
     * - payment.failed -> PaymentFailed/ ou payment_failed/ ou Payment_Failed/
     */
    public static function lifeCycle(): array
    {
        return [
            'paymentBegin' => ['amount', 'currency', 'userId'],
            'payment_complete' => ['transactionId', 'amount', 'currency'],
            'payment.failed' => ['error', 'amount', 'userId', 'attemptNumber'],
        ];
    }
    
    public function processPayment(float $amount, string $currency, int $userId): array
    {
        try {
            // Executa hooks do início do pagamento
            runHook($this, 'paymentBegin', $amount, $currency, $userId);
            
            // Simula processamento
            $transactionId = 'TXN-' . uniqid();
            
            // Executa hooks de completude
            runHook($this, 'payment_complete', $transactionId, $amount, $currency);
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency
            ];
            
        } catch (\Exception $e) {
            $attemptNumber = 1;
            
            // Executa hooks de falha
            runHook($this, 'payment.failed', $e, $amount, $userId, $attemptNumber);
            
            throw $e;
        }
    }
}