<?php

namespace PhpDiffused\Lifecycle;

use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use Illuminate\Support\Collection;

class LifeCycleManager
{
    protected $kernel = null;
    protected array $hookCache = [];
    protected array $hooks = [];
    
    public function __construct()
    {
        $this->kernel = new \App\Hooks\Kernel();
    }

    /**
     * @throws InvalidLifeCycleException
     * @throws HookExecutionException
     */
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

        $debugEnabled = function_exists('config') && config('lifecycle.debug', false);
        
        if ($debugEnabled) {
            $this->logLifecycleStart($className, $lifeCycle, $filteredHooks, $argsArray);
        }

        $filteredHooks->each(function (object $hook) use (&$argsArray, $lifeCycle, $className, $debugEnabled) {
            try {
                if ($debugEnabled) {
                    $beforeState = $this->captureVariableState($argsArray);
                    $this->logHookStart($hook, $beforeState);
                }
                
                $hook->handle($argsArray);
                
                if ($debugEnabled) {
                    $afterState = $this->captureVariableState($argsArray);
                    $this->logHookEnd($hook, $beforeState, $afterState);
                }
            } catch (\Throwable $e) {
                if ($debugEnabled) {
                    $this->logHookError($hook, $e);
                }
                $this->handleError($hook, $e, $lifeCycle, $className);
            }
        });
        
        if ($debugEnabled) {
            $this->logLifecycleEnd($className, $lifeCycle, $argsArray);
        }
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
    
    protected function logLifecycleStart(string $className, string $lifeCycle, Collection $hooks, array $args): void
    {
        if (function_exists('logger') && app()->bound('log')) {
            logger()->debug("=== [{$className}] Lifecycle '{$lifeCycle}' started ===", [
                'class' => $className,
                'lifecycle' => $lifeCycle,
                'hooks_count' => $hooks->count(),
                'hooks' => $hooks->map(fn($hook) => get_class($hook))->toArray(),
                'variables' => $this->formatVariables($args)
            ]);
        }
    }
    
    protected function logLifecycleEnd(string $className, string $lifeCycle, array $args): void
    {
        if (function_exists('logger') && app()->bound('log')) {
            logger()->debug("=== [{$className}] Lifecycle '{$lifeCycle}' completed ===", [
                'class' => $className,
                'lifecycle' => $lifeCycle,
                'final_variables' => $this->formatVariables($args)
            ]);
        }
    }
    
    protected function logHookStart(object $hook, array $beforeState): void
    {
        if (function_exists('logger') && app()->bound('log')) {
            $hookClass = get_class($hook);
            $severity = method_exists($hook, 'getSeverity') ? $hook->getSeverity() : 'optional';
            
            logger()->debug("→ [Hook] {$hookClass} executing...", [
                'hook' => $hookClass,
                'severity' => $severity,
                'variables_before' => $beforeState
            ]);
        }
    }
    
    protected function logHookEnd(object $hook, array $beforeState, array $afterState): void
    {
        if (function_exists('logger') && app()->bound('log')) {
            $hookClass = get_class($hook);
            $changes = $this->detectChanges($beforeState, $afterState);
            
            $message = "✓ [Hook] {$hookClass} completed";
            if (!empty($changes)) {
                $message .= " (modified variables)";
            }
            
            logger()->debug($message, [
                'hook' => $hookClass,
                'variables_after' => $afterState,
                'changes_detected' => !empty($changes),
                'changes' => $changes
            ]);
        }
    }
    
    protected function logHookError(object $hook, \Throwable $e): void
    {
        if (function_exists('logger') && app()->bound('log')) {
            $hookClass = get_class($hook);
            $severity = method_exists($hook, 'getSeverity') ? $hook->getSeverity() : 'optional';
            
            logger()->debug("✗ [Hook] {$hookClass} failed", [
                'hook' => $hookClass,
                'severity' => $severity,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    protected function captureVariableState(array $args): array
    {
        $state = [];
        foreach ($args as $key => $value) {
            $state[$key] = $this->serializeValue($value);
        }
        return $state;
    }
    
    protected function detectChanges(array $before, array $after): array
    {
        $changes = [];
        
        foreach ($before as $key => $beforeValue) {
            $afterValue = $after[$key] ?? null;
            if ($beforeValue !== $afterValue) {
                $changes[$key] = [
                    'before' => $beforeValue,
                    'after' => $afterValue
                ];
            }
        }
        
        return $changes;
    }
    
    protected function formatVariables(array $args): array
    {
        $formatted = [];
        foreach ($args as $key => $value) {
            $formatted[$key] = $this->serializeValue($value);
        }
        return $formatted;
    }
    
    protected function serializeValue($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_scalar($value)) {
            return (string) $value;
        }
        
        if (is_array($value)) {
            return 'array(' . count($value) . ' items)';
        }
        
        if (is_object($value)) {
            return get_class($value) . ' object';
        }
        
        return gettype($value);
    }
}