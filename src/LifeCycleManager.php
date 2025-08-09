<?php

namespace PhpDiffused\Lifecycle;

use Illuminate\Support\Collection;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;

class LifeCycleManager
{
    /**
     * Storage for hooks by class
     * @var array<string, Collection<int, LifeCycleHook>>
     */
    protected array $hooks = [];
    
    /**
     * @var LifeCycleServiceProvider
     */
    protected LifeCycleServiceProvider $provider;
    
    public function __construct(LifeCycleServiceProvider $provider)
    {
        $this->provider = $provider;
    }
    
    /**
     * Run hooks for a specific class and lifecycle
     * 
     * @param string|object $target Class name or instance
     * @param string $lifeCycle The lifecycle event name
     * @param mixed ...$args Arguments passed by reference
     * @throws InvalidLifeCycleException
     * @throws HookExecutionException
     */
    public function runHook($target, string $lifeCycle, &...$args): void
    {
        $className = is_object($target) ? get_class($target) : $target;

        if (!is_subclass_of($className, LifeCycle::class)) {
            throw new InvalidLifeCycleException(
                "Class '{$className}' must implement " . LifeCycle::class
            );
        }

        $lifeCycles = $className::lifeCycle();
        if (!array_key_exists($lifeCycle, $lifeCycles)) {
            throw new InvalidLifeCycleException(
                "LifeCycle '{$lifeCycle}' is not defined in {$className}"
            );
        }
        
        $expectedArgs = $lifeCycles[$lifeCycle];

        if (count($args) < count($expectedArgs)) {
            $missing = array_slice($expectedArgs, count($args));
            throw new InvalidLifeCycleException(
                "LifeCycle '{$lifeCycle}' expects arguments: " . implode(', ', $missing)
            );
        }

        $argsArray = [];
        foreach ($expectedArgs as $index => $argName) {
            $argsArray[$argName] = &$args[$index];
        }

        $hooks = $this->getHooksFor($className);

        $lifecycleHooks = $hooks->filter(fn(LifeCycleHook $hook) => $hook->getLifeCycle() === $lifeCycle);

        if ($this->provider->hooksKernel && isset($this->provider->hooksKernel->hooks[$className][$lifeCycle])) {
            $lifecycleHooks = $this->applyHookOrdering($lifecycleHooks, $className, $lifeCycle);
        }

        $lifecycleHooks->each(function (LifeCycleHook $hook) use (&$argsArray, $lifeCycle, $className) {
            try {
                $hook->handle($argsArray);
            } catch (\Throwable $e) {
                $this->handleError($hook, $e, $lifeCycle, $className);
            }
        });
    }
    
    /**
     * Get hooks for a specific class
     * 
     * @param string $className
     * @return Collection<int, LifeCycleHook>
     */
    public function getHooksFor(string $className): Collection
    {
        if (!isset($this->hooks[$className])) {
            $this->hooks[$className] = $this->provider->resolveHooksFor($className);
        }
        
        return $this->hooks[$className];
    }
    
    /**
     * Set the hooks kernel for ordering (used in tests)
     * 
     * @param mixed $kernel
     */
    public function setHooksKernel($kernel): void
    {
        $this->provider->hooksKernel = $kernel;
    }
    
    /**
     * Set hooks for a specific class
     * 
     * @param string $className
     * @param Collection $hooks
     */
    public function setHooksFor(string $className, Collection $hooks): void
    {
        $this->hooks[$className] = $hooks;
    }
    
    /**
     * Add a hook for a specific class
     * 
     * @param string $className
     * @param LifeCycleHook $hook
     */
    public function addHook(string $className, LifeCycleHook $hook): void
    {
        if (!isset($this->hooks[$className])) {
            $this->hooks[$className] = collect();
        }
        
        $this->hooks[$className]->push($hook);
    }
    
    /**
     * Remove all hooks for a specific lifecycle in a class
     * 
     * @param string $className
     * @param string $lifeCycle
     */
    public function removeHooksFor(string $className, string $lifeCycle): void
    {
        if (isset($this->hooks[$className])) {
            $this->hooks[$className] = $this->hooks[$className]
                ->reject(fn(LifeCycleHook $hook) => $hook->getLifeCycle() === $lifeCycle);
        }
    }
    
    /**
     * Apply hook ordering based on kernel configuration
     * 
     * @param Collection $hooks
     * @param string $className
     * @param string $lifeCycle
     * @return Collection
     */
    protected function applyHookOrdering(Collection $hooks, string $className, string $lifeCycle): Collection
    {
        $orderedHooks = collect();
        $hookOrder = $this->provider->hooksKernel->hooks[$className][$lifeCycle] ?? [];

        foreach ($hookOrder as $orderedHookClass) {
            $hook = $hooks->first(fn(LifeCycleHook $h) => get_class($h) === $orderedHookClass);
            if ($hook) {
                $orderedHooks->push($hook);
            }
        }

        $hooks->each(function (LifeCycleHook $hook) use ($orderedHooks, $hookOrder) {
            if (!in_array(get_class($hook), $hookOrder) && !$orderedHooks->contains($hook)) {
                $orderedHooks->push($hook);
            }
        });
        
        return $orderedHooks;
    }
    
    /**
     * Handle errors during hook execution
     * 
     * @param LifeCycleHook $hook
     * @param \Throwable $e
     * @param string $lifeCycle
     * @param string $className
     * @throws HookExecutionException
     */
    protected function handleError(LifeCycleHook $hook, \Throwable $e, string $lifeCycle, string $className): void
    {
        if ($hook->getSeverity() === 'critical') {
            throw new HookExecutionException(
                "Critical hook failed in lifecycle '{$lifeCycle}' for class '{$className}': " . $e->getMessage(),
                previous: $e
            );
        }
        
        if (function_exists('logger') && app()->bound('log')) {
            logger()->error("Hook failed in lifecycle '{$lifeCycle}' for class '{$className}'", [
                'hook' => get_class($hook),
                'severity' => $hook->getSeverity(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}