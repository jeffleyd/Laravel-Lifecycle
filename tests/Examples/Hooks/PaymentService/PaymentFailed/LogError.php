<?php

namespace App\Hooks\PaymentService\PaymentFailed;

use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

/**
 * Hook que registra erros de pagamento
 * Localizado em: Hooks/PaymentService/PaymentFailed/LogError.php
 * 
 * Ciclo de vida: payment.failed
 * Pasta correspondente: PaymentFailed/ (PascalCase - sem pontos)
 * 
 * IMPORTANTE: Pastas não podem conter pontos, então:
 * payment.failed -> PaymentFailed/ ou payment_failed/ ou Payment_Failed/
 */
class LogError implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'payment.failed';
    }
    
    public function getSeverity(): string
    {
        return 'critical';
    }
    
    public function handle(array &$args): void
    {
        $error = $args['error'];
        $amount = $args['amount'];
        $userId = $args['userId'];
        $attemptNumber = $args['attemptNumber'];
        
        // Log crítico do erro
        echo "❌ ERRO DE PAGAMENTO REGISTRADO\n";
        echo "👤 Usuário: {$userId}\n";
        echo "💰 Valor: {$amount}\n";
        echo "🔄 Tentativa: {$attemptNumber}\n";
        echo "⚠️ Erro: " . $error->getMessage() . "\n";
        echo "📅 Data: " . date('Y-m-d H:i:s') . "\n";
        
        // Simula salvamento em log
        $logData = [
            'user_id' => $userId,
            'amount' => $amount,
            'error' => $error->getMessage(),
            'attempt' => $attemptNumber,
            'timestamp' => time()
        ];
        
        echo "💾 Log salvo: " . json_encode($logData) . "\n";
    }
}