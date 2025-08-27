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
        
        // Simular envio de email
        $this->sendPaymentConfirmationEmail($userId, $amount, $paymentId);
        
        echo "📧 Email de confirmação enviado para usuário {$userId} - Pagamento: {$paymentId}\n";
    }
    
    private function sendPaymentConfirmationEmail(int $userId, float $amount, string $paymentId): void
    {
        // Aqui você implementaria o envio real do email
        // Por exemplo, usando Laravel Mail
        
        /*
        Mail::to($user->email)->send(new PaymentConfirmationMail([
            'user_id' => $userId,
            'amount' => $amount,
            'payment_id' => $paymentId
        ]));
        */
    }
}
