<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;

/**
 * Optional calculation hook that applies business rules
 * 
 * This hook modifies calculation results by applying a 10% increase.
 * Useful for taxes, fees, or other business rule calculations.
 */
#[Hook(scope: ExampleService::class, point: 'before_calculate', severity: Severity::Optional)]
class CalculationHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        $args['result'] = $args['amount'] * 1.10;
    }
}
