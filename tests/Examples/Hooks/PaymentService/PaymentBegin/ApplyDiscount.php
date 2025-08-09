<?php

namespace App\Hooks\PaymentService\PaymentBegin;

use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

/**
 * Hook que aplica desconto baseado no usuário
 * Localizado em: Hooks/PaymentService/PaymentBegin/ApplyDiscount.php
 */
class ApplyDiscount implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'paymentBegin';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $userId = $args['userId'] ?? 0;
        $originalAmount = $args['amount'];
        
        // Simula verificação de usuário VIP
        if ($userId % 10 === 0) { // Usuários com ID múltiplo de 10 são VIP
            $discount = $originalAmount * 0.1; // 10% de desconto
            $args['amount'] = $originalAmount - $discount;
            
            echo "🎉 Desconto VIP aplicado: -{$discount} {$args['currency']}\n";
            echo "💰 Novo valor: {$args['amount']} {$args['currency']}\n";
        }
    }
}