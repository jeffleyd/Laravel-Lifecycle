# Laravel LifeCycle Hooks

[![Latest Version](https://img.shields.io/packagist/v/php-diffused/lifecycle)](https://packagist.org/packages/php-diffused/lifecycle)
[![Total Downloads](https://img.shields.io/packagist/dt/php-diffused/lifecycle)](https://packagist.org/packages/php-diffused/lifecycle)
[![PHP Version](https://img.shields.io/packagist/php-v/php-diffused/lifecycle)](https://packagist.org/packages/php-diffused/lifecycle)
[![License](https://img.shields.io/packagist/l/php-diffused/lifecycle)](https://github.com/php-diffused/lifecycle/blob/main/LICENSE)
[![Tests](https://img.shields.io/github/actions/workflow/status/php-diffused/lifecycle/tests.yml?branch=main&label=tests)](https://github.com/php-diffused/lifecycle/actions)

> Allow multiple developers to inject behaviors into specific lifecycle points without modifying core business logic.

## The Problem

When multiple developers need to add behaviors to a service:
- **Traditional approach**: Everyone modifies the same class, causing conflicts and complexity
- **LifeCycle Hooks**: Core logic stays untouched, behaviors are injected via hooks

## Quick Example

```php
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;

#[LifeCyclePoint('before_payment', ['user_id', 'amount'])]
#[LifeCyclePoint('after_payment', ['user_id', 'amount', 'payment_id'])]
class PaymentService
{
    use HasLifecycle;
    
    public function process(int $userId, float $amount): string
    {
        runHook($this, 'before_payment', $userId, $amount);
        
        $paymentId = $this->doPayment($userId, $amount);
        
        runHook($this, 'after_payment', $userId, $amount, $paymentId);
        
        return $paymentId;
    }
}
```

```php
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;

#[Hook(scope: 'PaymentService', point: 'before_payment', severity: Severity::Critical)]
class FraudDetectionHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        if ($this->isFraudulent($args)) {
            throw new FraudException('Suspicious activity detected');
        }
    }
}
```

## Installation

```bash
composer require php-diffused/lifecycle
```

```bash
php artisan vendor:publish --provider="PhpDiffused\Lifecycle\LifeCycleServiceProvider"
```

The configuration and kernel files are published automatically during installation.

## Key Features

### Hook Severity
- **Critical**: Must succeed or the entire operation fails
- **Optional**: Failures are logged but don't stop execution

### Centralized Control
All hooks are registered in `app/Hooks/Kernel.php`:

```php
public array $hooks = [
    \App\Services\PaymentService::class => [
        'before_payment' => [
            \App\Hooks\ValidateAmountHook::class,    // Runs first
            \App\Hooks\FraudDetectionHook::class,    // Runs second
            \App\Hooks\ApplyDiscountHook::class,     // Runs third
        ],
    ],
];
```

### Artisan Commands

```bash
# Generate a service with lifecycle points
php artisan lifecycle:main App/Services/PaymentService

# Generate a hook
php artisan lifecycle:hook FraudDetectionHook --scope=payment --point=before_payment --severity=Critical

# Analyze hooks and detect conflicts
php artisan lifecycle:analyze App/Services/PaymentService
```

### Analyze Output

```
Analyzing lifecycle for: App\Services\PaymentService

Lifecycle Points (3):
  • before_payment: [user_id, amount]
  • after_payment: [user_id, amount, payment_id]

Hook Analysis:
  • before_payment: 2 hooks (1 critical, 1 optional)
    - FraudDetectionHook [critical]
    - ApplyDiscountHook [optional]

Potential Issues:
  ⚠️  Multiple hooks modify 'amount' in before_payment
     Order matters! Check execution sequence in Kernel.
```

## Advanced Usage

### Mutable Hooks

Hooks can modify values passed by reference:

```php
#[Hook(scope: 'PaymentService', point: 'before_payment')]
class ApplyDiscountHook
{
    public function handle(array &$args): void
    {
        $args['amount'] *= 0.9;  // 10% discount
    }
}
```

### Dynamic Hook Management

```php
// Add hooks at runtime
addHook(PaymentService::class, new CustomHook());

// Remove hooks for specific lifecycle
removeHooksFor(PaymentService::class, 'payment_failed');
```

### External Execution

Execute hooks from anywhere:

```php
// In a controller
public function processPayment(Request $request)
{
    $amount = $request->input('amount');
    
    // Execute hooks without instantiating the service
    runHook(PaymentService::class, 'before_payment', auth()->id(), $amount);
    
    $service = app(PaymentService::class);
    return $service->process(auth()->id(), $amount);
}
```

## When to Use

**Use LifeCycle Hooks for:**
- Complex business processes (checkout, onboarding, provisioning)
- Multi-team systems requiring isolated customizations
- Features that need to be toggled without code changes

**Don't use for:**
- Simple CRUD operations
- Microservices with single responsibilities
- Performance-critical paths

## Configuration

```php
// config/lifecycle.php
return [
    'error_handling' => [
        'log_failures' => true,
        'throw_on_critical' => true,
    ],
    'debug' => env('LIFECYCLE_DEBUG', false),
];
```

## Testing

```bash
composer test
composer test:coverage
```

## License

MIT License - see [LICENSE](LICENSE) for details.

---

[Documentation](https://docs.php-diffused.com) • [GitHub](https://github.com/php-diffused/lifecycle) • [Discord](https://discord.gg/php-diffused)