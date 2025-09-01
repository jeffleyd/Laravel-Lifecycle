<?php

namespace App\Services;

use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;

#[LifeCyclePoint('before_payment', ['user_id', 'amount'])]
#[LifeCyclePoint('after_payment', ['user_id', 'amount', 'payment_id'])]
#[LifeCyclePoint('payment_failed', ['user_id', 'amount', 'error'])]
class PaymentService
{
    use HasLifecycle;

    /**
     * @throws InvalidLifeCycleException
     * @throws HookExecutionException
     */
    public function processPayment(int $userId, float $amount): array
    {
        runHook($this, 'before_payment', $userId, $amount);

        try {
            $paymentId = $this->executePayment($userId, $amount);

            runHook($this, 'after_payment', $userId, $amount, $paymentId);

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'message' => 'Payment processed successfully'
            ];

        } catch (\Exception $e) {
            runHook($this, 'payment_failed', $userId, $amount, $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * @throws \Exception
     */
    private function executePayment(int $userId, float $amount): string
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Value must be greater than zero');
        }

        if (rand(1, 10) === 1) {
            throw new \Exception('Payment gateway communication failure');
        }

        return 'PAY_' . uniqid();
    }
}
