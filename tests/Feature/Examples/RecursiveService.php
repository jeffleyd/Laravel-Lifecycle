<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples;

use PhpDiffused\Lifecycle\Traits\HasLifecycle;

/**
 * Service for testing recursive hook execution
 * 
 * This service is used to test that hooks can be called recursively
 * and that the system properly tracks execution counts.
 */
class RecursiveService
{
    use HasLifecycle;

    public static function lifeCycle(): array
    {
        return ['on_recursion' => ['level']];
    }

    public function triggerRecursion(int $level): void
    {
        if ($level > 0) {
            runHook(self::class, 'on_recursion', $level);
            $this->triggerRecursion($level - 1);
        }
    }
}
