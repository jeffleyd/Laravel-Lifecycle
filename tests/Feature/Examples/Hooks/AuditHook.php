<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;

/**
 * Critical audit hook for compliance tracking
 * 
 * This hook ensures all operations are properly audited.
 * As a critical hook, it must execute successfully for compliance.
 */
#[Hook(scope: ExampleService::class, point: 'after_process', severity: Severity::Critical)]
class AuditHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        $args['result']['audit_id'] = 'AUDIT_' . uniqid();
        $args['result']['audited'] = true;
    }
}
