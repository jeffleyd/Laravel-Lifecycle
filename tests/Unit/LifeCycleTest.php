<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;

class LifeCycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->manager->setHooksFor(TestService::class, collect());
    }
    
    public function test_can_run_hooks_for_valid_lifecycle(): void
    {
        $hook = new TestHook();
        addHook(TestService::class, $hook);
        
        $param1 = 'value1';
        $param2 = 'value2';
        runHook(TestService::class, 'test_lifecycle', $param1, $param2);
        
        $this->assertTrue($hook->wasExecuted());
        $this->assertEquals([
            'param1' => 'value1',
            'param2' => 'value2'
        ], $hook->getReceivedArgs());
    }
    
    public function test_throws_exception_for_invalid_lifecycle(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("LifeCycle 'invalid_lifecycle' is not defined");
        
        runHook(TestService::class, 'invalid_lifecycle');
    }
    
    public function test_throws_exception_for_missing_arguments(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("LifeCycle 'test_lifecycle' expects arguments: param2");
        
        $param1 = 'value1';
        runHook(TestService::class, 'test_lifecycle', $param1);
    }
    
    public function test_critical_hook_failure_throws_exception(): void
    {
        $hook = new CriticalFailingHook();
        addHook(TestService::class, $hook);
        
        $this->expectException(HookExecutionException::class);
        $this->expectExceptionMessage("Critical hook failed in lifecycle 'test_lifecycle'");
        
        $param1 = 'value1';
        $param2 = 'value2';
        runHook(TestService::class, 'test_lifecycle', $param1, $param2);
    }
    
    public function test_optional_hook_failure_does_not_throw_exception(): void
    {
        $hook = new OptionalFailingHook();
        addHook(TestService::class, $hook);
        
        $param1 = 'value1';
        $param2 = 'value2';
        runHook(TestService::class, 'test_lifecycle', $param1, $param2);
        
        $this->assertTrue(true);
    }
    
    public function test_can_add_and_remove_hooks(): void
    {
        $hook1 = new TestHook();
        $hook2 = new TestHook();
        
        addHook(TestService::class, $hook1);
        addHook(TestService::class, $hook2);
        
        $this->assertCount(2, $this->manager->getHooksFor(TestService::class));
        
        removeHooksFor(TestService::class, 'test_lifecycle');
        
        $this->assertCount(0, $this->manager->getHooksFor(TestService::class));
    }
    
    public function test_hooks_are_filtered_by_lifecycle(): void
    {
        $testHook = new TestHook();
        $otherHook = new OtherLifeCycleHook();
        
        addHook(TestService::class, $testHook);
        addHook(TestService::class, $otherHook);
        
        $param1 = 'value1';
        $param2 = 'value2';
        runHook(TestService::class, 'test_lifecycle', $param1, $param2);
        
        $this->assertTrue($testHook->wasExecuted());
        $this->assertFalse($otherHook->wasExecuted());
    }
    
    public function test_can_run_hooks_with_instance(): void
    {
        $hook = new TestHook();
        $service = new TestService();
        
        addHook($service, $hook);
        
        $param1 = 'value1';
        $param2 = 'value2';
        runHook($service, 'test_lifecycle', $param1, $param2);
        
        $this->assertTrue($hook->wasExecuted());
        $this->assertEquals([
            'param1' => 'value1',
            'param2' => 'value2'
        ], $hook->getReceivedArgs());
    }
}

class TestService implements LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'test_lifecycle' => ['param1', 'param2'],
            'other_lifecycle' => ['param3'],
        ];
    }
}

class TestHook implements LifeCycleHook
{
    private bool $executed = false;
    private array $receivedArgs = [];
    
    public function getLifeCycle(): string
    {
        return 'test_lifecycle';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $this->executed = true;
        $this->receivedArgs = $args;
    }
    
    public function wasExecuted(): bool
    {
        return $this->executed;
    }
    
    public function getReceivedArgs(): array
    {
        return $this->receivedArgs;
    }
}

class CriticalFailingHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'test_lifecycle';
    }
    
    public function getSeverity(): string
    {
        return 'critical';
    }
    
    public function handle(array &$args): void
    {
        throw new \Exception('Critical hook failed');
    }
}

class OptionalFailingHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'test_lifecycle';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        throw new \Exception('Optional hook failed');
    }
}

class OtherLifeCycleHook implements LifeCycleHook
{
    private bool $executed = false;
    
    public function getLifeCycle(): string
    {
        return 'other_lifecycle';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {
        $this->executed = true;
    }
    
    public function wasExecuted(): bool
    {
        return $this->executed;
    }
}