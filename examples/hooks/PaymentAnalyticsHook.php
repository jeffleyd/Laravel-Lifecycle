<?php

namespace App\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;

#[Hook(scope: 'PaymentService', point: 'after_payment', severity: Severity::Optional)]
class PaymentAnalyticsHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $userId = $args['user_id'];
        $amount = $args['amount'];
        $paymentId = $args['payment_id'];

        $this->trackPaymentEvent($userId, $amount, $paymentId);
        
        echo "📊 Analytics events - Payment: {$paymentId}\n";
    }
    
    private function trackPaymentEvent(int $userId, float $amount, string $paymentId): void
    {
        $eventData = [
            'event' => 'payment_completed',
            'user_id' => $userId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'currency' => 'BRL',
            'timestamp' => now()->toISOString()
        ];
    }
}
