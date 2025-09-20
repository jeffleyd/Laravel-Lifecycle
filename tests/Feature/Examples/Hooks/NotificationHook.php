<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;

/**
 * Optional notification hook that modifies messages
 * 
 * This hook adds a system prefix to notification messages.
 * Demonstrates how hooks can transform data in the pipeline.
 */
#[Hook(scope: ExampleService::class, point: 'before_notify', severity: Severity::Optional)]
class NotificationHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        $args['message'] = '[SYSTEM] ' . $args['message'];
    }
}
