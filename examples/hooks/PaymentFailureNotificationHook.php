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
        
        // Notificar sobre falha no pagamento
        $this->notifyPaymentFailure($userId, $amount, $error);
        
        echo "🚨 Notificação de falha enviada para usuário {$userId} - Erro: {$error}\n";
    }
    
    private function notifyPaymentFailure(int $userId, float $amount, string $error): void
    {
        // Múltiplas formas de notificação
        
        // 1. Email para o usuário
        $this->sendFailureEmail($userId, $amount, $error);
        
        // 2. Notificação push (se aplicável)
        $this->sendPushNotification($userId, $error);
        
        // 3. Notificar equipe de suporte em casos críticos
        if ($this->isCriticalError($error)) {
            $this->notifySupportTeam($userId, $amount, $error);
        }
        
        // 4. Log estruturado para monitoramento
        $this->logPaymentFailure($userId, $amount, $error);
    }
    
    private function sendFailureEmail(int $userId, float $amount, string $error): void
    {
        // Implementar envio de email de falha
        /*
        Mail::to($user->email)->send(new PaymentFailureMail([
            'user_id' => $userId,
            'amount' => $amount,
            'error' => $error,
            'retry_link' => route('payment.retry', ['user' => $userId])
        ]));
        */
    }
    
    private function sendPushNotification(int $userId, string $error): void
    {
        // Implementar notificação push
        /*
        PushNotification::send($userId, [
            'title' => 'Falha no Pagamento',
            'body' => 'Houve um problema com seu pagamento. Tente novamente.',
            'action' => 'retry_payment'
        ]);
        */
    }
    
    private function isCriticalError(string $error): bool
    {
        $criticalErrors = [
            'gateway de pagamento',
            'timeout',
            'conexão',
            'sistema indisponível'
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
        // Notificar equipe de suporte
        /*
        Slack::send('#payments-alerts', [
            'text' => "🚨 Falha crítica no pagamento",
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        ['title' => 'Usuário', 'value' => $userId, 'short' => true],
                        ['title' => 'Valor', 'value' => "R$ {$amount}", 'short' => true],
                        ['title' => 'Erro', 'value' => $error, 'short' => false]
                    ]
                ]
            ]
        ]);
        */
    }
    
    private function logPaymentFailure(int $userId, float $amount, string $error): void
    {
        // Log estruturado para monitoramento
        logger()->error('Payment failure', [
            'user_id' => $userId,
            'amount' => $amount,
            'error' => $error,
            'timestamp' => now(),
            'context' => 'payment_processing'
        ]);
    }
}
