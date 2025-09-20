<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\LifeCycleManager;
use PhpDiffused\Lifecycle\LifeCycleServiceProvider;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use Illuminate\Support\Collection;

class LifeCycleManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
    
    public function test_can_run_hooks_with_class_name(): void
    {
        $hook = new ManagerTestHook();
        $this->manager->setHooksFor(ManagerTestService::class, collect([$hook]));
        
        $param1 = 'value1';
        $param2 = 'value2';
        
        $this->manager->runHook(ManagerTestService::class, 'test.begin', $param1, $param2);
        
        $this->assertTrue($hook->wasExecuted());
        $this->assertEquals([
            'param1' => 'value1',
            'param2' => 'value2'
        ], $hook->getReceivedArgs());
    }
    
    public function test_can_run_hooks_with_instance(): void
    {
        $hook = new ManagerTestHook();
        $service = new ManagerTestService();
        
        $this->manager->setHooksFor(ManagerTestService::class, collect([$hook]));
        
        $param1 = 'value1';
        $param2 = 'value2';
        
        $this->manager->runHook($service, 'test.begin', $param1, $param2);
        
        $this->assertTrue($hook->wasExecuted());
        $this->assertEquals([
            'param1' => 'value1',
            'param2' => 'value2'
        ], $hook->getReceivedArgs());
    }
    
    public function test_throws_exception_for_non_lifecycle_class(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("Class 'stdClass' must define lifecycle points");
        
        $this->manager->runHook(\stdClass::class, 'some.event');
    }
    
    public function test_throws_exception_for_invalid_lifecycle(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("LifeCycle 'invalid.event' is not defined");
        
        $this->manager->runHook(ManagerTestService::class, 'invalid.event');
    }
    
    public function test_throws_exception_for_missing_arguments(): void
    {
        $this->expectException(InvalidLifeCycleException::class);
        $this->expectExceptionMessage("LifeCycle 'test.begin' expects arguments: param2");
        
        $param1 = 'value1';
        $this->manager->runHook(ManagerTestService::class, 'test.begin', $param1);
    }
    
    public function test_can_add_hook_dynamically(): void
    {
        $hook = new ManagerTestHook();

        $this->assertCount(0, $this->manager->getHooksFor(ManagerTestService::class));

        $this->manager->addHook(ManagerTestService::class, $hook);

        $hooks = $this->manager->getHooksFor(ManagerTestService::class);
        $this->assertCount(1, $hooks);
        $this->assertSame($hook, $hooks->first());
    }
    
    public function test_can_remove_hooks_for_lifecycle(): void
    {
        $hook1 = new ManagerTestHook();
        $hook2 = new ManagerCompleteHook();
        
        $this->manager->setHooksFor(ManagerTestService::class, collect([$hook1, $hook2]));

        $this->assertCount(2, $this->manager->getHooksFor(ManagerTestService::class));

        $this->manager->removeHooksFor(ManagerTestService::class, 'test.begin');

        $hooks = $this->manager->getHooksFor(ManagerTestService::class);
        $this->assertCount(1, $hooks);
        $this->assertSame($hook2, $hooks->first());
    }
    
    public function test_handles_critical_hook_failure(): void
    {
        $criticalHook = new ManagerCriticalFailingHook();
        $this->manager->setHooksFor(ManagerTestService::class, collect([$criticalHook]));
        
        $this->expectException(HookExecutionException::class);
        $this->expectExceptionMessage("Critical hook failed in lifecycle 'test.begin'");
        
        $param1 = 'value1';
        $param2 = 'value2';
        $this->manager->runHook(ManagerTestService::class, 'test.begin', $param1, $param2);
    }
    
    public function test_handles_optional_hook_failure_silently(): void
    {
        $optionalHook = new ManagerOptionalFailingHook();
        $this->manager->setHooksFor(ManagerTestService::class, collect([$optionalHook]));
        
        $param1 = 'value1';
        $param2 = 'value2';

        $this->manager->runHook(ManagerTestService::class, 'test.begin', $param1, $param2);
        
        $this->assertTrue(true);
    }
    
    public function test_passes_arguments_by_reference(): void
    {
        $hook = new ManagerMutatingHook();
        $this->manager->setHooksFor(ManagerTestService::class, collect([$hook]));
        
        $param1 = 'original';
        $param2 = 'unchanged';
        
        $this->manager->runHook(ManagerTestService::class, 'test.begin', $param1, $param2);
        
        $this->assertEquals('modified', $param1);
        $this->assertEquals('unchanged', $param2);
    }
}

#[LifeCyclePoint('test.begin', ['param1', 'param2'])]
#[LifeCyclePoint('test.complete', ['result'])]
class ManagerTestService
{
    use HasLifecycle;
}

#[Hook(scope: 'ManagerTestService', point: 'test.begin', severity: Severity::Optional)]
class ManagerTestHook
{
    use Hookable;
    
    private bool $executed = false;
    private array $receivedArgs = [];
    
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

#[Hook(scope: 'ManagerTestService', point: 'test.complete', severity: Severity::Optional)]
class ManagerCompleteHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
    }
}

#[Hook(scope: 'ManagerTestService', point: 'test.begin', severity: Severity::Critical)]
class ManagerCriticalFailingHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        throw new \Exception('Critical failure in hook');
    }
}

#[Hook(scope: 'ManagerTestService', point: 'test.begin', severity: Severity::Optional)]
class ManagerOptionalFailingHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        throw new \Exception('Optional failure in hook');
    }
}

#[Hook(scope: 'ManagerTestService', point: 'test.begin', severity: Severity::Optional)]
class ManagerMutatingHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args['param1'] = 'modified';
    }
}