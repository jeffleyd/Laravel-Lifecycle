<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;

/**
 * Optional data transformation hook
 * 
 * This hook adds metadata and transforms the result data structure.
 * Demonstrates how hooks can enrich data after processing.
 */
#[Hook(scope: ExampleService::class, point: 'after_process', severity: Severity::Optional)]
class DataTransformHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        $args['result']['processed_by'] = 'DataTransformHook';
        $args['result']['data_count'] = count($args['data']);
    }
}
