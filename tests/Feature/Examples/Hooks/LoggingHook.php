<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;

/**
 * Optional logging hook for audit trail
 * 
 * This hook adds logging metadata to track when operations occur.
 * As an optional hook, failures won't stop the execution flow.
 */
#[Hook(scope: ExampleService::class, point: 'before_process', severity: Severity::Optional)]
class LoggingHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        $args['options']['logged'] = true;
        $args['options']['log_time'] = microtime(true);
    }
}
