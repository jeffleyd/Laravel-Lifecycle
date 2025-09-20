<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples;

use PhpDiffused\Lifecycle\Traits\HasLifecycle;

/**
 * Service for testing hook execution order
 * 
 * This service is used to test that hooks execute in the correct sequence
 * and that failures in critical hooks stop the execution chain.
 */
class SequentialService
{
    use HasLifecycle;

    public static function lifeCycle(): array
    {
        return ['on_sequence' => []];
    }

    public function runSequence(): void
    {
        runHook(self::class, 'on_sequence');
    }
}
