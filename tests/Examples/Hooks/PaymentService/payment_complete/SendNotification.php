<?php

namespace App\Hooks\PaymentService\payment_complete;

use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

/**
 * Hook que envia notificação de pagamento
 * Localizado em: Hooks/PaymentService/payment_complete/SendNotification.php
 * 
 * Ciclo de vida: payment_complete
 * Pasta correspondente: payment_complete/ (snake_case mantido)
 */
class SendNotification implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'payment_complete';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $transactionId = $args['transactionId'];
        $amount = $args['amount'];
        $currency = $args['currency'];
        
        // Simula envio de email
        echo "📧 Email enviado: Pagamento de {$amount} {$currency} processado\n";
        echo "🔗 ID da transação: {$transactionId}\n";
        
        // Simula push notification
        echo "🔔 Push notification enviada\n";
    }
}