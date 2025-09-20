<?php

namespace PhpDiffused\Lifecycle\Tests\Feature;

use PhpDiffused\Lifecycle\Tests\Feature\Examples\ExampleService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\SequentialService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\RecursiveService;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ValidationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\LoggingHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\CalculationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\NotificationHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\DataTransformHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\AuditHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\FirstHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\SecondHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\ThirdHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\FourthHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\FifthHook;
use PhpDiffused\Lifecycle\Tests\Feature\Examples\Hooks\RecursiveHook;

/**
 * Test kernel for registering example hooks
 * 
 * This kernel defines which hooks are attached to which lifecycle points
 * for the example services used in testing.
 */
class TestKernel
{
    public array $hooks = [
        ExampleService::class => [
            'before_validate' => [
                ValidationHook::class,
            ],
            'before_process' => [
                LoggingHook::class,
            ],
            'after_process' => [
                DataTransformHook::class,
                AuditHook::class,
            ],
            'before_calculate' => [
                CalculationHook::class,
            ],
            'before_notify' => [
                NotificationHook::class,
            ],
        ],

        SequentialService::class => [
            'on_sequence' => [
                FirstHook::class,
                SecondHook::class,
                ThirdHook::class,
                FourthHook::class,
                FifthHook::class,
            ],
        ],

        RecursiveService::class => [
            'on_recursion' => [
                RecursiveHook::class,
            ],
        ],
    ];
}