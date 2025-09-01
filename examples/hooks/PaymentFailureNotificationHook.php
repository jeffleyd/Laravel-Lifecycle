<?php

namespace App\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;

#[Hook(scope: 'PaymentService', point: 'payment_failed', severity: Severity::Optional)]
class PaymentFailureNotificationHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $userId = $args['user_id'];
        $amount = $args['amount'];
        $error = $args['error'];

        $this->notifyPaymentFailure($userId, $amount, $error);
        
        echo "🚨 Notification user {$userId} - Error: {$error}\n";
    }
    
    private function notifyPaymentFailure(int $userId, float $amount, string $error): void
    {

        $this->sendFailureEmail($userId, $amount, $error);

        $this->sendPushNotification($userId, $error);

        if ($this->isCriticalError($error)) {
            $this->notifySupportTeam($userId, $amount, $error);
        }

        $this->logPaymentFailure($userId, $amount, $error);
    }
    
    private function sendFailureEmail(int $userId, float $amount, string $error): void
    {

    }
    
    private function sendPushNotification(int $userId, string $error): void
    {

    }
    
    private function isCriticalError(string $error): bool
    {
        $criticalErrors = [
            'gateway',
            'timeout',
            'connection',
            'unavailable'
        ];
        
        foreach ($criticalErrors as $criticalError) {
            if (stripos($error, $criticalError) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function notifySupportTeam(int $userId, float $amount, string $error): void
    {

    }
    
    private function logPaymentFailure(int $userId, float $amount, string $error): void
    {
        logger()->error('Payment failure', [
            'user_id' => $userId,
            'amount' => $amount,
            'error' => $error,
            'timestamp' => now(),
            'context' => 'payment_processing'
        ]);
    }
}
