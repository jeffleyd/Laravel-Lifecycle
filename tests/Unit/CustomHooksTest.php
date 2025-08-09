<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

class CustomHooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock config service for testing (disable auto-discovery)
        $this->container->bind('config', function () {
            return new class {
                public function get($key, $default = null) {
                    return match ($key) {
                        'lifecycle.auto_discovery' => false, // Disable to avoid app_path issues
                        'lifecycle.cache.enabled' => false,
                        default => $default
                    };
                }
            };
        });
        
        // Configure test kernel with custom hooks
        $testKernel = new class {
            public array $hooks = [
                TestServiceWithCustomHooks::class => [
                    'process' => [
                        CustomHookFromAnotherLocation::class,
                        AnotherCustomHook::class,
                    ]
                ]
            ];
        };
        
        $this->provider->hooksKernel = $testKernel;
    }
    
    public function test_kernel_can_register_custom_hooks(): void
    {
        $hooks = $this->provider->resolveHooksFor(TestServiceWithCustomHooks::class);
        
        $this->assertCount(2, $hooks);
        
        $hookClasses = $hooks->map(fn($hook) => get_class($hook))->toArray();
        $this->assertContains(CustomHookFromAnotherLocation::class, $hookClasses);
        $this->assertContains(AnotherCustomHook::class, $hookClasses);
    }
    
    public function test_custom_hooks_can_be_executed(): void
    {
        CustomHookFromAnotherLocation::$executed = false;
        AnotherCustomHook::$executed = false;
        
        $data = 'test_data';
        runHook(TestServiceWithCustomHooks::class, 'process', $data);
        
        $this->assertTrue(CustomHookFromAnotherLocation::$executed);
        $this->assertTrue(AnotherCustomHook::$executed);
    }
    
    public function test_custom_hooks_are_executed_in_kernel_order(): void
    {
        CustomHookFromAnotherLocation::$executionOrder = [];
        AnotherCustomHook::$executionOrder = [];
        
        $data = 'test_data';
        runHook(TestServiceWithCustomHooks::class, 'process', $data);
        
        $allExecutions = array_merge(
            CustomHookFromAnotherLocation::$executionOrder,
            AnotherCustomHook::$executionOrder
        );
        
        // Sort by timestamp to get execution order
        asort($allExecutions);
        $executedClasses = array_keys($allExecutions);
        
        $this->assertEquals([
            CustomHookFromAnotherLocation::class,
            AnotherCustomHook::class
        ], $executedClasses);
    }
}

class TestServiceWithCustomHooks implements LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'process' => ['data'],
        ];
    }
}

class CustomHookFromAnotherLocation implements LifeCycleHook
{
    public static bool $executed = false;
    public static array $executionOrder = [];
    
    public function getLifeCycle(): string
    {
        return 'process';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        self::$executed = true;
        self::$executionOrder[self::class] = microtime(true);
    }
}

class AnotherCustomHook implements LifeCycleHook
{
    public static bool $executed = false;
    public static array $executionOrder = [];
    
    public function getLifeCycle(): string
    {
        return 'process';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        self::$executed = true;
        self::$executionOrder[self::class] = microtime(true);
    }
}