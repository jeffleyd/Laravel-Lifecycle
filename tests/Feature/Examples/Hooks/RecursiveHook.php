<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\RecursiveService;

/**
 * Hook for testing recursive execution
 * 
 * This hook is called recursively to test that the system
 * properly handles and tracks recursive hook execution.
 */
#[Hook(scope: RecursiveService::class, point: 'on_recursion', severity: Severity::Optional)]
class RecursiveHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        // Recursive hook execution
    }
}
