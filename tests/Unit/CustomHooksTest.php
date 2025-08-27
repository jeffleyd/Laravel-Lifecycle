<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;

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
        // Verificar se o kernel foi configurado corretamente
        $this->assertNotNull($this->provider->hooksKernel);
        $this->assertArrayHasKey(TestServiceWithCustomHooks::class, $this->provider->hooksKernel->hooks);
        
        $kernelHooks = $this->provider->hooksKernel->hooks[TestServiceWithCustomHooks::class]['process'];
        $this->assertContains(CustomHookFromAnotherLocation::class, $kernelHooks);
        $this->assertContains(AnotherCustomHook::class, $kernelHooks);
    }
    
    public function test_custom_hooks_can_be_executed(): void
    {
        CustomHookFromAnotherLocation::$executed = false;
        AnotherCustomHook::$executed = false;
        
        // Configurar o kernel no manager
        $this->manager->setHooksKernel($this->provider->hooksKernel);
        
        $data = 'test_data';
        runHook(TestServiceWithCustomHooks::class, 'process', $data);
        
        $this->assertTrue(CustomHookFromAnotherLocation::$executed);
        $this->assertTrue(AnotherCustomHook::$executed);
    }
    
    public function test_custom_hooks_are_executed_in_kernel_order(): void
    {
        CustomHookFromAnotherLocation::$executionOrder = [];
        AnotherCustomHook::$executionOrder = [];
        
        // Configurar o kernel no manager
        $this->manager->setHooksKernel($this->provider->hooksKernel);
        
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

#[LifeCyclePoint('process', ['data'])]
class TestServiceWithCustomHooks
{
    use HasLifecycle;
}

#[Hook(scope: 'TestServiceWithCustomHooks', point: 'process', severity: Severity::Optional)]
class CustomHookFromAnotherLocation
{
    use Hookable;
    
    public static bool $executed = false;
    public static array $executionOrder = [];
    
    public function handle(array &$args): void
    {
        self::$executed = true;
        self::$executionOrder[self::class] = microtime(true);
    }
}

#[Hook(scope: 'TestServiceWithCustomHooks', point: 'process', severity: Severity::Optional)]
class AnotherCustomHook
{
    use Hookable;
    
    public static bool $executed = false;
    public static array $executionOrder = [];
    
    public function handle(array &$args): void
    {
        self::$executed = true;
        self::$executionOrder[self::class] = microtime(true);
    }
}