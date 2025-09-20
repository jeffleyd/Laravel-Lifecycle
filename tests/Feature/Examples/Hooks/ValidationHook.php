<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;

/**
 * Critical validation hook that ensures data integrity
 * 
 * This hook validates input data and adds validation metadata.
 * As a critical hook, any failure will stop the execution flow.
 */
#[Hook(scope: ExampleService::class, point: 'before_validate', severity: Severity::Critical)]
class ValidationHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        if (empty($args['data'])) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }
        
        $args['data']['validated_at'] = time();
    }
}
