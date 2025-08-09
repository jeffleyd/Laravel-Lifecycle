<?php

namespace App\Hooks\PaymentService\PaymentBegin;

use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

/**
 * Hook que valida o valor do pagamento
 * Localizado em: Hooks/PaymentService/PaymentBegin/ValidateAmount.php
 * 
 * Ciclo de vida: paymentBegin
 * Pasta correspondente: PaymentBegin/ (camelCase convertido)
 */
class ValidateAmount implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'paymentBegin';
    }
    
    public function getSeverity(): string
    {
        return 'critical';
    }
    
    public function handle(array &$args): void
    {
        $amount = $args['amount'] ?? 0;
        
        if ($amount <= 0) {
            throw new \InvalidArgumentException('O valor do pagamento deve ser maior que zero');
        }
        
        if ($amount > 10000) {
            throw new \InvalidArgumentException('Valor muito alto - requer aprovação manual');
        }
        
        // Log da validação
        echo "✅ Valor validado: {$amount} {$args['currency']}\n";
    }
}