<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples;

/**
 * Service without lifecycle definition for error testing
 * 
 * This service intentionally lacks the HasLifecycle trait and lifecycle
 * definition to test error handling for invalid lifecycle usage.
 */
class InvalidService
{
    public function doSomething(): void
    {
        runHook(self::class, 'undefined_point');
    }
}
