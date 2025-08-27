<?php

namespace PhpDiffused\Lifecycle;

use Illuminate\Support\Collection;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;

class LifeCycleManager
{
    protected $kernel = null;
    protected array $hookCache = [];
    protected array $hooks = [];
    
    public function __construct()
    {
        if (class_exists(\App\Hooks\Kernel::class)) {
            $this->kernel = new \App\Hooks\Kernel();
        }
    }
    
    public function runHook($target, string $lifeCycle, &...$args): void
    {
        $className = is_object($target) ? get_class($target) : $target;

        if (!$this->classHasLifecycle($className)) {
            throw new InvalidLifeCycleException(
                "Class '{$className}' must define lifecycle points"
            );
        }

        $lifeCycles = $this->getLifeCyclePoints($className);
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

        $kernelHooks = $this->getHooksFromKernel($className, $lifeCycle);
        
        if ($kernelHooks->isEmpty()) {
            $manualHooks = $this->getHooksFor($className);
            $filteredHooks = $manualHooks->filter(function(object $hook) use ($lifeCycle) {
                if (method_exists($hook, 'getLifeCycle')) {
                    return $hook->getLifeCycle() === $lifeCycle;
                }
                return false;
            });
        } else {
            $filteredHooks = $kernelHooks;
        }

        if (function_exists('config') && config('lifecycle.debug', false)) {
            $this->logDebugInfo($className, $lifeCycle, $filteredHooks, $argsArray);
        }

        $filteredHooks->each(function (object $hook) use (&$argsArray, $lifeCycle, $className) {
            try {
                $hook->handle($argsArray);
            } catch (\Throwable $e) {
                $this->handleError($hook, $e, $lifeCycle, $className);
            }
        });
    }
    
    protected function classHasLifecycle(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new \ReflectionClass($className);
        $traits = $reflection->getTraitNames();
        
        if (in_array(HasLifecycle::class, $traits)) {
            return true;
        }
        
        return method_exists($className, 'lifeCycle');
    }
    
    protected function getLifeCyclePoints(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        $reflection = new \ReflectionClass($className);
        $traits = $reflection->getTraitNames();
        
        if (in_array(HasLifecycle::class, $traits)) {
            return $className::lifeCycle();
        }
        
        if (method_exists($className, 'lifeCycle')) {
            return $className::lifeCycle();
        }
        
        return [];
    }
    
    protected function getHooksFromKernel(string $className, string $lifeCycle): Collection
    {
        $cacheKey = "{$className}.{$lifeCycle}";
        
        if (isset($this->hookCache[$cacheKey])) {
            return $this->hookCache[$cacheKey];
        }
        
        $hooks = collect();
        
        if (!$this->kernel || !isset($this->kernel->hooks[$className][$lifeCycle])) {
            $this->hookCache[$cacheKey] = $hooks;
            return $hooks;
        }
        
        $hookClasses = $this->kernel->hooks[$className][$lifeCycle];
        
        foreach ($hookClasses as $hookClass) {
            try {
                if (class_exists($hookClass)) {
                    $hookInstance = app($hookClass);
                    $hooks->push($hookInstance);
                }
            } catch (\Throwable $e) {
                if ((!function_exists('config') || config('lifecycle.error_handling.log_failures', true)) && function_exists('logger') && app()->bound('log')) {
                    logger()->warning("Failed to instantiate kernel hook: {$hookClass}", [
                        'error' => $e->getMessage(),
                        'service' => $className,
                        'lifecycle' => $lifeCycle
                    ]);
                }
            }
        }
        
        $this->hookCache[$cacheKey] = $hooks;
        
        return $hooks;
    }
    
    public function setHooksKernel($kernel): void
    {
        $this->kernel = $kernel;
        $this->clearCache();
    }
    
    public function clearCache(): void
    {
        $this->hookCache = [];
    }
    
    public function setHooksFor(string $className, Collection $hooks): void
    {
        $this->hooks[$className] = $hooks;
    }
    
    public function getHooksFor(string $className): Collection
    {
        if (!isset($this->hooks[$className])) {
            $this->hooks[$className] = collect();
        }
        
        return $this->hooks[$className];
    }
    
    public function addHook(string $className, object $hook): void
    {
        if (!isset($this->hooks[$className])) {
            $this->hooks[$className] = collect();
        }
        
        $this->hooks[$className]->push($hook);
    }
    
    public function removeHooksFor(string $className, string $lifeCycle): void
    {
        if (isset($this->hooks[$className])) {
            $this->hooks[$className] = $this->hooks[$className]
                ->reject(function(object $hook) use ($lifeCycle) {
                    if (method_exists($hook, 'getLifeCycle')) {
                        return $hook->getLifeCycle() === $lifeCycle;
                    }
                    return false;
                });
        }
    }
    
    protected function handleError(object $hook, \Throwable $e, string $lifeCycle, string $className): void
    {
        $severity = 'optional';
        
        if (method_exists($hook, 'getSeverity')) {
            $severity = $hook->getSeverity();
        }
        
        if ($severity === 'critical' && (!function_exists('config') || config('lifecycle.error_handling.throw_on_critical', true))) {
            throw new HookExecutionException(
                "Critical hook failed in lifecycle '{$lifeCycle}' for class '{$className}': " . $e->getMessage(),
                previous: $e
            );
        }
        
        if ((!function_exists('config') || config('lifecycle.error_handling.log_failures', true)) && function_exists('logger') && app()->bound('log')) {
            logger()->error("Hook failed in lifecycle '{$lifeCycle}' for class '{$className}'", [
                'hook' => get_class($hook),
                'severity' => $severity,
                'error' => $e->getMessage(),
                'trace' => (function_exists('config') && config('lifecycle.debug', false)) ? $e->getTraceAsString() : null
            ]);
        }
    }
    
    protected function logDebugInfo(string $className, string $lifeCycle, Collection $hooks, array $args): void
    {
        if (function_exists('logger') && app()->bound('log')) {
            logger()->debug("Executing hooks for lifecycle '{$lifeCycle}' in class '{$className}'", [
                'class' => $className,
                'lifecycle' => $lifeCycle,
                'hooks_count' => $hooks->count(),
                'hooks' => $hooks->map(fn($hook) => get_class($hook))->toArray(),
                'arguments' => array_keys($args)
            ]);
        }
    }
}