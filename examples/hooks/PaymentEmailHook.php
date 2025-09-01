<?php

namespace App\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;

#[Hook(scope: 'PaymentService', point: 'after_payment', severity: Severity::Optional)]
class PaymentEmailHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $userId = $args['user_id'];
        $amount = $args['amount'];
        $paymentId = $args['payment_id'];

        $this->sendPaymentConfirmationEmail($userId, $amount, $paymentId);
        
        echo "📧 Confirmation email {$userId} - Payment: {$paymentId}\n";
    }
    
    private function sendPaymentConfirmationEmail(int $userId, float $amount, string $paymentId): void
    {
    }
}
