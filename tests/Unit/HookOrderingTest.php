<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

class HookOrderingTest extends TestCase
{
    protected static array $executionOrder = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        self::$executionOrder = [];

        $this->manager->setHooksFor(OrderedService::class, collect());

        $this->manager->setHooksKernel(new TestHooksKernel());
    }
    
    public function test_hooks_execute_in_defined_order(): void
    {
        addHook(OrderedService::class, new FirstHook());
        addHook(OrderedService::class, new SecondHook());
        addHook(OrderedService::class, new ThirdHook());
        
        $value = 'test';
        runHook(OrderedService::class, 'process', $value);
        
        $this->assertEquals(['first', 'second', 'third'], self::$executionOrder);
    }
    
    public function test_mixed_ordered_and_unordered_hooks(): void
    {
        addHook(OrderedService::class, new ThirdHook());
        addHook(OrderedService::class, new UnorderedHook());
        addHook(OrderedService::class, new FirstHook());
        addHook(OrderedService::class, new SecondHook());
        
        $value = 'test';
        runHook(OrderedService::class, 'process', $value);

        $this->assertContains('first', self::$executionOrder);
        $this->assertContains('second', self::$executionOrder);
        
        $firstIndex = array_search('first', self::$executionOrder);
        $secondIndex = array_search('second', self::$executionOrder);
        
        $this->assertLessThan($secondIndex, $firstIndex);
    }
    
    public function test_multiple_lifecycles_with_different_orders(): void
    {
        addHook(OrderedService::class, new BeforeHookA());
        addHook(OrderedService::class, new BeforeHookB());
        addHook(OrderedService::class, new AfterHookA());
        addHook(OrderedService::class, new AfterHookB());
        
        $value = 'test';

        self::$executionOrder = [];
        runHook(OrderedService::class, 'before', $value);
        $this->assertEquals(['before-b', 'before-a'], self::$executionOrder);
        
        // Execute 'after' lifecycle
        self::$executionOrder = [];
        runHook(OrderedService::class, 'after', $value);
        $this->assertEquals(['after-a', 'after-b'], self::$executionOrder);
    }
    
    public static function recordExecution(string $hookName): void
    {
        self::$executionOrder[] = $hookName;
    }
}

class OrderedService implements LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'process' => ['value'],
            'before' => ['value'],
            'after' => ['value'],
        ];
    }
}

class TestHooksKernel
{
    public array $hookOrder = [
        OrderedService::class => [
            'process' => [
                FirstHook::class,
                SecondHook::class,
            ],
            'before' => [
                BeforeHookB::class,
                BeforeHookA::class,
            ],
            'after' => [
                AfterHookA::class,
                AfterHookB::class,
            ],
        ],
    ];
}

abstract class RecordingHook implements LifeCycleHook
{
    protected string $name;
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution($this->name);
    }
}

class FirstHook extends RecordingHook
{
    protected string $name = 'first';
    
    public function getLifeCycle(): string
    {
        return 'process';
    }
}

class SecondHook extends RecordingHook
{
    protected string $name = 'second';
    
    public function getLifeCycle(): string
    {
        return 'process';
    }
}

class ThirdHook extends RecordingHook
{
    protected string $name = 'third';
    
    public function getLifeCycle(): string
    {
        return 'process';
    }
}

class UnorderedHook extends RecordingHook
{
    protected string $name = 'unordered';
    
    public function getLifeCycle(): string
    {
        return 'process';
    }
}

class BeforeHookA extends RecordingHook
{
    protected string $name = 'before-a';
    
    public function getLifeCycle(): string
    {
        return 'before';
    }
}

class BeforeHookB extends RecordingHook
{
    protected string $name = 'before-b';
    
    public function getLifeCycle(): string
    {
        return 'before';
    }
}

class AfterHookA extends RecordingHook
{
    protected string $name = 'after-a';
    
    public function getLifeCycle(): string
    {
        return 'after';
    }
}

class AfterHookB extends RecordingHook
{
    protected string $name = 'after-b';
    
    public function getLifeCycle(): string
    {
        return 'after';
    }
}