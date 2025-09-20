<?php

namespace PhpDiffused\Lifecycle\Testing;

use Illuminate\Support\Collection;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\LifeCycleManager;

class TestableLifeCycleManager extends LifeCycleManager
{
    protected $testInstance = null;
    public ?Severity $filterBySeverity = null;

    public function setTestInstance($testInstance): void
    {
        $this->testInstance = $testInstance;
    }

    protected function getTestProperty(string $property)
    {
        if ($this->testInstance && property_exists($this->testInstance, $property)) {
            $reflection = new \ReflectionClass($this->testInstance);
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);
                return $prop->getValue($this->testInstance);
            }
        }
        return null;
    }

    protected function setTestProperty(string $property, $value): void
    {
        if ($this->testInstance && property_exists($this->testInstance, $property)) {
            $reflection = new \ReflectionClass($this->testInstance);
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setValue($this->testInstance, $value);
            }
        }
    }

    public function runHook($target, string $lifeCycle, &...$args): void
    {
        $disabledHooks = $this->getTestProperty('disabledHooks') ?? [];

        if (in_array('*', $disabledHooks)) {
            return;
        }

        $className = is_object($target) ? get_class($target) : $target;

        if (!$this->classHasLifecycle($className)) {
            throw new \PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException(
                "Class '{$className}' must define lifecycle points"
            );
        }

        $lifeCycles = $this->getLifeCyclePoints($className);
        if (!array_key_exists($lifeCycle, $lifeCycles)) {
            throw new \PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException(
                "LifeCycle '{$lifeCycle}' is not defined in {$className}"
            );
        }
        
        $expectedArgs = $lifeCycles[$lifeCycle];

        while (count($args) < count($expectedArgs)) {
            $args[] = null;
        }
        
        $argsArray = [];
        foreach ($expectedArgs as $index => $argName) {
            $argsArray[$argName] = &$args[$index];
        }

        $hooks = $this->getHooksForExecution($className, $lifeCycle);

        $hooks = $this->filterTestHooks($hooks);

        $this->executeHooksWithInstrumentation($hooks, $className, $lifeCycle, $argsArray);
    }

    protected function filterTestHooks(Collection $hooks): Collection
    {
        $disabledHooks = $this->getTestProperty('disabledHooks') ?? [];

        return $hooks->filter(function ($hook) use ($disabledHooks) {
            $hookClass = get_class($hook);

            if (in_array($hookClass, $disabledHooks)) {
                return false;
            }

            if ($this->filterBySeverity !== null) {
                $severity = method_exists($hook, 'getSeverity')
                    ? $hook->getSeverity()
                    : 'optional';

                $hookSeverity = match($severity) {
                    'critical' => Severity::Critical,
                    'optional' => Severity::Optional,
                    default => Severity::Optional
                };

                return $hookSeverity === $this->filterBySeverity;
            }

            return true;
        });
    }

    protected function executeHooksWithInstrumentation(Collection $hooks, string $className, string $lifeCycle, array &$args): void
    {
        $hooks->each(function ($hook) use ($className, $lifeCycle, &$args) {
            $hookClass = get_class($hook);

            $hookReplacements = $this->getTestProperty('hookReplacements') ?? [];
            if (isset($hookReplacements[$hookClass])) {
                $replacement = $hookReplacements[$hookClass];
                $hook = $replacement->getReplacementHook();
            }

            $captureMetrics = $this->getTestProperty('captureMetrics') ?? false;
            $startTime = $captureMetrics ? microtime(true) : null;
            $startMemory = $captureMetrics ? memory_get_usage() : null;

            $captureMutations = $this->getTestProperty('captureMutations') ?? false;
            $beforeState = $captureMutations ? $this->deepCopy($args) : null;

            $captureSequence = $this->getTestProperty('captureSequence') ?? false;
            if ($captureSequence) {
                $executionSequence = $this->getTestProperty('executionSequence') ?? [];
                $executionSequence[] = [
                    'hook' => $hookClass,
                    'lifecycle' => $lifeCycle,
                    'timestamp' => microtime(true)
                ];
                $this->setTestProperty('executionSequence', $executionSequence);
            }

            $executionLog = $this->getTestProperty('executionLog') ?? [];
            $executionLog[] = [
                'hook' => $hookClass,
                'lifecycle' => $lifeCycle,
                'class' => $className,
                'args_before' => $captureMutations ? $beforeState : null,
            ];
            $this->setTestProperty('executionLog', $executionLog);

            try {
                $hook->handle($args);

                $hookSpies = $this->getTestProperty('hookSpies') ?? [];
                if (isset($hookSpies[$hookClass])) {
                    $hookSpies[$hookClass]->recordExecution($args);
                }

            } catch (\Throwable $e) {
                if (isset($hookReplacements[$hookClass])) {
                    $replacement = $hookReplacements[$hookClass];
                    if ($replacement instanceof HookMocker && $replacement->shouldFail) {
                        $severity = method_exists($hook, 'getSeverity') ? $hook->getSeverity() : 'optional';
                        if ($severity === 'critical') {
                            throw new \PhpDiffused\Lifecycle\Exceptions\HookExecutionException(
                                "Critical hook failed: " . $replacement->failureException->getMessage(),
                                previous: $replacement->failureException
                            );
                        }
                        return;
                    }
                }

                $severity = method_exists($hook, 'getSeverity') ? $hook->getSeverity() : 'optional';
                if ($severity === 'critical') {
                    throw new \PhpDiffused\Lifecycle\Exceptions\HookExecutionException(
                        "Critical hook failed: " . $e->getMessage(),
                        previous: $e
                    );
                }
            }

            if ($captureMetrics) {
                $hookMetrics = $this->getTestProperty('hookMetrics') ?? [];
                $hookMetrics[$hookClass] = [
                    'time' => (microtime(true) - $startTime) * 1000, // Convert to ms
                    'memory' => memory_get_usage() - $startMemory
                ];
                $this->setTestProperty('hookMetrics', $hookMetrics);
            }

            if ($captureMutations) {
                $mutations = $this->getTestProperty('mutations') ?? [];
                $mutations[$hookClass] = [
                    'before' => $beforeState,
                    'after' => $this->deepCopy($args)
                ];
                $this->setTestProperty('mutations', $mutations);
            }
        });
    }

    protected function getHooksForExecution(string $className, string $lifeCycle): Collection
    {
        $hooks = parent::getHooksFromKernel($className, $lifeCycle);

        if ($hooks->isEmpty()) {
            $manualHooks = $this->getHooksFor($className);
            $hooks = $manualHooks->filter(function($hook) use ($lifeCycle) {
                if (method_exists($hook, 'getLifeCycle')) {
                    return $hook->getLifeCycle() === $lifeCycle;
                }
                return false;
            });
        }

        return $hooks;
    }

    protected function deepCopy($data)
    {
        return unserialize(serialize($data));
    }
}