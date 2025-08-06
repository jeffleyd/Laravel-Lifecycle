<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use PhpDiffused\Lifecycle\Support\HasLifeCycleHooks;

class HookOrderingTest extends TestCase
{
    private OrderedService $service;
    private static array $executionOrder = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OrderedService();
        self::$executionOrder = [];
    }
    
    public function test_hooks_execute_in_defined_order(): void
    {
        $hook3 = new OrderedHook3();
        $hook1 = new OrderedHook1();
        $hook2 = new OrderedHook2();
        
        $this->service->addHook($hook1);
        $this->service->addHook($hook2);
        $this->service->addHook($hook3);
        
        $args = ['value' => 100];
        $this->service->runHook('process', $args);
        
        $this->assertEquals(['Hook1', 'Hook2', 'Hook3'], self::$executionOrder);
        
        // Hook1: 100 * 2 = 200
        // Hook2: 200 + 50 = 250
        // Hook3: 250 - 25 = 225
        $this->assertEquals(225, $args['value']);
    }
    
    public function test_mixed_ordered_and_unordered_hooks(): void
    {
        // Hooks ordenados
        $hook1 = new OrderedHook1();
        $hook3 = new OrderedHook3();
        
        $hookExtra = new ExtraHook();
        
        $this->service->addHook($hook1);
        $this->service->addHook($hook3);
        $this->service->addHook($hookExtra);
        
        $args = ['value' => 100];
        $this->service->runHook('process', $args);
        
        // Verifica ordem de execução
        $this->assertEquals(['Hook1', 'Hook3', 'ExtraHook'], self::$executionOrder);
        

        // Hook1: 100 * 2 = 200
        // Hook3: 200 - 25 = 175
        // ExtraHook: 175 * 1.1 = 192.5
        $this->assertEqualsWithDelta(192.5, $args['value'], 0.0001);
    }
    
    public function test_multiple_lifecycles_with_different_orders(): void
    {
        $beforeHook1 = new BeforeHook1();
        $beforeHook2 = new BeforeHook2();
        $afterHook1 = new AfterHook1();
        $afterHook2 = new AfterHook2();
        
        $this->service->addHook($beforeHook2);
        $this->service->addHook($beforeHook1);
        $this->service->addHook($afterHook1);
        $this->service->addHook($afterHook2);
        
        $args = ['value' => 100];
        
        self::$executionOrder = [];
        $this->service->runHook('before', $args);
        $this->assertEquals(['BeforeHook2', 'BeforeHook1'], self::$executionOrder);
        
        self::$executionOrder = [];
        $this->service->runHook('after', $args);
        $this->assertEquals(['AfterHook1', 'AfterHook2'], self::$executionOrder);
    }
    
    public static function recordExecution(string $hookName): void
    {
        self::$executionOrder[] = $hookName;
    }
}

class OrderedService implements LifeCycle
{
    use HasLifeCycleHooks;
    
    public static function lifeCycle(): array
    {
        return [
            'process' => ['value'],
            'before' => ['value'],
            'after' => ['value'],
        ];
    }
}

class OrderedHook1 implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'process'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('Hook1');
        $args['value'] *= 2;
    }
}

class OrderedHook2 implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'process'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('Hook2');
        $args['value'] += 50;
    }
}

class OrderedHook3 implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'process'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('Hook3');
        $args['value'] -= 25;
    }
}

class ExtraHook implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'process'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('ExtraHook');
        $args['value'] *= 1.1;
    }
}

class BeforeHook1 implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('BeforeHook1');
    }
}

class BeforeHook2 implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'before'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('BeforeHook2');
    }
}

class AfterHook1 implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'after'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('AfterHook1');
    }
}

class AfterHook2 implements LifeCycleHook
{
    public function getLifeCycle(): string { return 'after'; }
    public function getSeverity(): string { return 'optional'; }
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution('AfterHook2');
    }
}