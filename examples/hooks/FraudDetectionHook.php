<?php

namespace App\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;

#[Hook(scope: 'PaymentService', point: 'before_payment', severity: Severity::Critical)]
class FraudDetectionHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $userId = $args['user_id'];
        $amount = $args['amount'];

        $this->performFraudChecks($userId, $amount);
        
        echo "🛡️ Fraud verification for user: {$userId} - Amout: USD {$amount}\n";
    }
    
    private function performFraudChecks(int $userId, float $amount): void
    {
        if ($amount > 10000) {
            throw new \Exception("Suspect amount: USD {$amount}");
        }

        $suspiciousUsers = [999, 998, 997];
        if (in_array($userId, $suspiciousUsers)) {
            throw new \Exception("User {$userId} is suspect list");
        }
    }
}
