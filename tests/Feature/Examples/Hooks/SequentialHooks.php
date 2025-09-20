<?php

namespace PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\SequentialService;

/**
 * First hook in sequence - optional
 */
#[Hook(scope: SequentialService::class, point: 'on_sequence', severity: Severity::Optional)]
class FirstHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        // First hook in sequence
    }
}

/**
 * Second hook in sequence - optional
 */
#[Hook(scope: SequentialService::class, point: 'on_sequence', severity: Severity::Optional)]
class SecondHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        // Second hook in sequence
    }
}

/**
 * Third hook in sequence - critical (can stop execution)
 */
#[Hook(scope: SequentialService::class, point: 'on_sequence', severity: Severity::Critical)]
class ThirdHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        // Third hook in sequence - critical
    }
}

/**
 * Fourth hook in sequence - optional
 */
#[Hook(scope: SequentialService::class, point: 'on_sequence', severity: Severity::Optional)]
class FourthHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        // Fourth hook in sequence
    }
}

/**
 * Fifth hook in sequence - optional
 */
#[Hook(scope: SequentialService::class, point: 'on_sequence', severity: Severity::Optional)]
class FifthHook
{
    use Hookable;

    public function handle(array &$args): void
    {
        // Fifth hook in sequence
    }
}
