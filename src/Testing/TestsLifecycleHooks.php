<?php

namespace PhpDiffused\Lifecycle\Testing;

use PhpDiffused\Lifecycle\LifeCycleManager;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PHPUnit\Framework\Assert;
use Mockery;

trait TestsLifecycleHooks
{
    protected ?LifeCycleManager $originalManager = null;
    protected ?TestableLifeCycleManager $testManager = null;
    protected array $hookSpies = [];
    protected array $disabledHooks = [];
    protected array $hookReplacements = [];
    protected array $executionLog = [];
    protected bool $captureMetrics = false;
    protected array $hookMetrics = [];
    protected bool $captureSequence = false;
    protected array $executionSequence = [];
    protected bool $captureMutations = false;
    protected array $mutations = [];

    /**
     * Setup lifecycle testing environment
     */
    protected function setUpLifecycleHooks(): void
    {
        $this->originalManager = app(LifeCycleManager::class);
        $this->testManager = new TestableLifeCycleManager();

        if ($this->originalManager && property_exists($this->originalManager, 'kernel')) {
            $reflection = new \ReflectionClass($this->originalManager);
            $kernelProp = $reflection->getProperty('kernel');
            $kernel = $kernelProp->getValue($this->originalManager);
            $this->testManager->setHooksKernel($kernel);
        }

        $this->testManager->setTestInstance($this);

        app()->instance(LifeCycleManager::class, $this->testManager);

        $this->resetHookState();
    }

    /**
     * Tear down lifecycle testing environment
     */
    protected function tearDownLifecycleHooks(): void
    {
        if ($this->originalManager) {
            app()->instance(LifeCycleManager::class, $this->originalManager);
        }

        Mockery::close();
    }

    /**
     * Reset all hook state
     */
    protected function resetHookState(): void
    {
        $this->hookSpies = [];
        $this->disabledHooks = [];
        $this->hookReplacements = [];
        $this->executionLog = [];
        $this->hookMetrics = [];
        $this->executionSequence = [];
        $this->mutations = [];
        $this->captureMetrics = false;
        $this->captureSequence = false;
        $this->captureMutations = false;
    }

    /**
     * Disable all hooks or specific hooks
     */
    public function withoutHooks($hooks = null): self
    {
        if ($hooks === null) {
            $this->disabledHooks = ['*'];
        } else {
            $hooks = is_array($hooks) ? $hooks : [$hooks];
            $this->disabledHooks = array_merge($this->disabledHooks, $hooks);
        }

        return $this;
    }

    /**
     * Only run hooks with specific severity
     */
    public function onlyHooksWithSeverity(Severity $severity): self
    {
        $this->testManager->filterBySeverity = $severity;
        return $this;
    }

    /**
     * Spy on a specific hook
     */
    public function spyHook(string $hookClass): HookSpy
    {
        $spy = new HookSpy($hookClass, $this);
        $this->hookSpies[$hookClass] = $spy;
        return $spy;
    }

    /**
     * Mock a hook with custom behavior
     */
    public function mockHook(string $hookClass): HookMocker
    {
        $mocker = new HookMocker($hookClass, $this);
        $this->hookReplacements[$hookClass] = $mocker;
        return $mocker;
    }

    /**
     * Replace a hook with a closure
     */
    public function replaceHook(string $hookClass): HookReplacer
    {
        $replacer = new HookReplacer($hookClass, $this);
        $this->hookReplacements[$hookClass] = $replacer;
        return $replacer;
    }

    /**
     * Capture hook execution sequence
     */
    public function captureHookSequence(): self
    {
        $this->captureSequence = true;
        $this->executionSequence = [];
        return $this;
    }

    /**
     * Assert hooks executed in specific order
     */
    public function assertHooksExecutedInOrder(array $expectedOrder): void
    {
        $actualOrder = array_map(fn($item) => $item['hook'], $this->executionSequence);

        Assert::assertEquals(
            $expectedOrder,
            $actualOrder,
            "Hooks did not execute in expected order.\nExpected: " . json_encode($expectedOrder) .
            "\nActual: " . json_encode($actualOrder)
        );
    }

    /**
     * Profile hook performance
     */
    public function profileHooks(): self
    {
        $this->captureMetrics = true;
        $this->hookMetrics = [];
        return $this;
    }

    /**
     * Assert hook execution time
     */
    public function assertHookExecutionTime(string $hookClass, string $operator, float $milliseconds): void
    {
        Assert::assertArrayHasKey(
            $hookClass,
            $this->hookMetrics,
            "Hook {$hookClass} was not executed or metrics were not captured"
        );

        $actualTime = $this->hookMetrics[$hookClass]['time'];

        switch ($operator) {
            case '<':
                Assert::assertLessThan($milliseconds, $actualTime);
                break;
            case '>':
                Assert::assertGreaterThan($milliseconds, $actualTime);
                break;
            case '<=':
                Assert::assertLessThanOrEqual($milliseconds, $actualTime);
                break;
            case '>=':
                Assert::assertGreaterThanOrEqual($milliseconds, $actualTime);
                break;
            case '==':
                Assert::assertEquals($milliseconds, $actualTime, '', 0.1);
                break;
        }
    }

    /**
     * Get hook performance metrics
     */
    public function getHookMetrics(): array
    {
        return $this->hookMetrics;
    }

    /**
     * Capture hook mutations
     */
    public function captureHookMutations(): self
    {
        $this->captureMutations = true;
        $this->mutations = [];
        return $this;
    }

    /**
     * Get mutations for a specific hook
     */
    public function getHookMutations(string $hookClass): ?object
    {
        if (!isset($this->mutations[$hookClass])) {
            return null;
        }

        return (object) $this->mutations[$hookClass];
    }

    /**
     * Enable debug mode for hooks
     */
    public function debugHooks(): HookDebugger
    {
        return new HookDebugger($this);
    }

    /**
     * Get hook execution trace
     */
    public function getHookTrace(): array
    {
        return $this->executionLog;
    }

    /**
     * Create a test scenario
     */
    public function withHookScenario(string $scenario): HookScenarioBuilder
    {
        return new HookScenarioBuilder($scenario, $this);
    }

    /**
     * Assert a hook was executed
     */
    public function assertExecuted(string $hookClass): void
    {
        $executed = collect($this->executionLog)
            ->pluck('hook')
            ->contains($hookClass);

        Assert::assertTrue(
            $executed,
            "Expected hook {$hookClass} to be executed, but it wasn't"
        );
    }

    /**
     * Assert a hook was not executed
     */
    public function assertNotExecuted(string $hookClass): void
    {
        $executed = collect($this->executionLog)
            ->pluck('hook')
            ->contains($hookClass);

        Assert::assertFalse(
            $executed,
            "Expected hook {$hookClass} not to be executed, but it was"
        );
    }
}