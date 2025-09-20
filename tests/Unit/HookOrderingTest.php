<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;

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
        addHook(OrderedService::class, new ThirdHook());

        $value = 'test';
        runHook(OrderedService::class, 'process', $value);
        
        $this->assertEquals(['first', 'second', 'third'], self::$executionOrder);
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
        
                self::$executionOrder = [];
        runHook(OrderedService::class, 'after', $value);
        $this->assertEquals(['after-a', 'after-b'], self::$executionOrder);
    }
    
    public static function recordExecution(string $hookName): void
    {
        self::$executionOrder[] = $hookName;
    }
}

#[LifeCyclePoint('process', ['value'])]
#[LifeCyclePoint('before', ['value'])]
#[LifeCyclePoint('after', ['value'])]
class OrderedService
{
    use HasLifecycle;
}

class TestHooksKernel
{
    public array $hooks = [
        OrderedService::class => [
            'process' => [
                FirstHook::class,
                SecondHook::class,
                ThirdHook::class,
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

abstract class RecordingHook
{
    use Hookable;
    
    protected string $name;
    
    public function handle(array &$args): void
    {
        HookOrderingTest::recordExecution($this->name);
    }
}

#[Hook(scope: OrderedService::class, point: 'process', severity: Severity::Optional)]
class FirstHook extends RecordingHook
{
    protected string $name = 'first';
    
    public function __construct()
    {
        $this->name = 'first';
    }
}

#[Hook(scope: OrderedService::class, point: 'process', severity: Severity::Optional)]
class SecondHook extends RecordingHook
{
    protected string $name = 'second';
    
    public function __construct()
    {
        $this->name = 'second';
    }
}

#[Hook(scope: OrderedService::class, point: 'process', severity: Severity::Optional)]
class ThirdHook extends RecordingHook
{
    protected string $name = 'third';
    
    public function __construct()
    {
        $this->name = 'third';
    }
}

#[Hook(scope: OrderedService::class, point: 'process', severity: Severity::Optional)]
class UnorderedHook extends RecordingHook
{
    protected string $name = 'unordered';
    
    public function __construct()
    {
        $this->name = 'unordered';
    }
}

#[Hook(scope: OrderedService::class, point: 'before', severity: Severity::Optional)]
class BeforeHookA extends RecordingHook
{
    protected string $name = 'before-a';
    
    public function __construct()
    {
        $this->name = 'before-a';
    }
}

#[Hook(scope: OrderedService::class, point: 'before', severity: Severity::Optional)]
class BeforeHookB extends RecordingHook
{
    protected string $name = 'before-b';
    
    public function __construct()
    {
        $this->name = 'before-b';
    }
}

#[Hook(scope: OrderedService::class, point: 'after', severity: Severity::Optional)]
class AfterHookA extends RecordingHook
{
    protected string $name = 'after-a';
    
    public function __construct()
    {
        $this->name = 'after-a';
    }
}

#[Hook(scope: OrderedService::class, point: 'after', severity: Severity::Optional)]
class AfterHookB extends RecordingHook
{
    protected string $name = 'after-b';
    
    public function __construct()
    {
        $this->name = 'after-b';
    }
}