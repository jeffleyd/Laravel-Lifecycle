<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use PhpDiffused\Lifecycle\Support\HasLifeCycleHooks;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;

class LifeCycleTest extends TestCase
{
    private TestService $service;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TestService();
    }
    
    public function test_can_run_hooks_for_valid_lifecycle(): void
    {
        $hook = new TestHook();
        $this->service->addHook($hook);
        
        $param1 = 'value1';
        $param2 = 'value2';
        $this->service->runHook('test_lifecycle', $param1, $param2);
        
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
        
        $this->service->runHook('invalid_lifecycle');
    }
    
    public function test_throws_exception_for_missing_arguments(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("LifeCycle 'test_lifecycle' expects arguments: param2");
        
        $param1 = 'value1';
        // Missing param2
        $this->service->runHook('test_lifecycle', $param1);
    }
    
    public function test_critical_hook_failure_throws_exception(): void
    {
        $hook = new CriticalFailingHook();
        $this->service->addHook($hook);
        
        $this->expectException(HookExecutionException::class);
        $this->expectExceptionMessage("Critical hook failed in lifecycle 'test_lifecycle'");
        
        $param1 = 'value1';
        $param2 = 'value2';
        $this->service->runHook('test_lifecycle', $param1, $param2);
    }
    
    public function test_optional_hook_failure_does_not_throw_exception(): void
    {
        $hook = new OptionalFailingHook();
        $this->service->addHook($hook);
        
        $param1 = 'value1';
        $param2 = 'value2';
        $this->service->runHook('test_lifecycle', $param1, $param2);
        
        $this->assertTrue(true);
    }
    
    public function test_can_add_and_remove_hooks(): void
    {
        $hook1 = new TestHook();
        $hook2 = new TestHook();
        
        $this->service->addHook($hook1);
        $this->service->addHook($hook2);
        
        $this->assertCount(2, $this->service->getHooks());
        
        $this->service->removeHooksFor('test_lifecycle');
        
        $this->assertCount(0, $this->service->getHooks());
    }
    
    public function test_hooks_are_filtered_by_lifecycle(): void
    {
        $testHook = new TestHook();
        $otherHook = new OtherLifeCycleHook();
        
        $this->service->addHook($testHook);
        $this->service->addHook($otherHook);
        
        $param1 = 'value1';
        $param2 = 'value2';
        $this->service->runHook('test_lifecycle', $param1, $param2);
        
        $this->assertTrue($testHook->wasExecuted());
        $this->assertFalse($otherHook->wasExecuted());
    }
}

class TestService implements LifeCycle
{
    use HasLifeCycleHooks;
    
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