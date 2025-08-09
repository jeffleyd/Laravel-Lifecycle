<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpDiffused\Lifecycle\LifeCycleManager;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use Illuminate\Container\Container;

class HelpersTest extends TestCase
{
    private Container $container;
    private LifeCycleManager $manager;
    
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Container::getInstance();

        $this->manager = $this->createMock(LifeCycleManager::class);

        $this->container->singleton(LifeCycleManager::class, function () {
            return $this->manager;
        });
    }
    
    protected function tearDown(): void
    {
        Container::setInstance();
        parent::tearDown();
    }
    
    public function test_runHook_calls_manager_with_class_name(): void
    {
        $className = HelpersTestService::class;
        $lifeCycle = 'test.begin';
        $param1 = 'value1';
        $param2 = 'value2';
        
        $this->manager->expects($this->once())
            ->method('runHook')
            ->with(
                $this->equalTo($className),
                $this->equalTo($lifeCycle),
                $this->anything(),
                $this->anything()
            );
        
        runHook($className, $lifeCycle, $param1, $param2);
    }
    
    public function test_runHook_calls_manager_with_instance(): void
    {
        $instance = new HelpersTestService();
        $lifeCycle = 'test.begin';
        $param1 = 'value1';
        $param2 = 'value2';
        
        $this->manager->expects($this->once())
            ->method('runHook')
            ->with(
                $this->equalTo($instance),
                $this->equalTo($lifeCycle),
                $this->anything(),
                $this->anything()
            );
        
        runHook($instance, $lifeCycle, $param1, $param2);
    }
    
    public function test_addHook_calls_manager_with_class_name(): void
    {
        $className = HelpersTestService::class;
        $hook = new HelpersTestHook();
        
        $this->manager->expects($this->once())
            ->method('addHook')
            ->with(
                $this->equalTo($className),
                $this->equalTo($hook)
            );
        
        addHook($className, $hook);
    }
    
    public function test_addHook_calls_manager_with_instance(): void
    {
        $instance = new HelpersTestService();
        $hook = new HelpersTestHook();
        
        $expectedClassName = get_class($instance);
        
        $this->manager->expects($this->once())
            ->method('addHook')
            ->with(
                $this->equalTo($expectedClassName),
                $this->equalTo($hook)
            );
        
        addHook($instance, $hook);
    }
    
    public function test_removeHooksFor_calls_manager_with_class_name(): void
    {
        $className = HelpersTestService::class;
        $lifeCycle = 'test.begin';
        
        $this->manager->expects($this->once())
            ->method('removeHooksFor')
            ->with(
                $this->equalTo($className),
                $this->equalTo($lifeCycle)
            );
        
        removeHooksFor($className, $lifeCycle);
    }
    
    public function test_removeHooksFor_calls_manager_with_instance(): void
    {
        $instance = new HelpersTestService();
        $lifeCycle = 'test.begin';
        
        $expectedClassName = get_class($instance);
        
        $this->manager->expects($this->once())
            ->method('removeHooksFor')
            ->with(
                $this->equalTo($expectedClassName),
                $this->equalTo($lifeCycle)
            );
        
        removeHooksFor($instance, $lifeCycle);
    }
}

class HelpersTestService implements LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'test.begin' => ['param1', 'param2'],
            'test.complete' => ['result'],
        ];
    }
}

class HelpersTestHook implements LifeCycleHook
{
    public function getLifeCycle(): string
    {
        return 'test.begin';
    }
    
    public function getSeverity(): string
    {
        return 'optional';
    }
    
    public function handle(array &$args): void
    {

    }
}