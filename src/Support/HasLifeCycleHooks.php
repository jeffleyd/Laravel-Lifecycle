<?php

namespace PhpDiffused\Lifecycle\Support;

use Illuminate\Support\Collection;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use PhpDiffused\Lifecycle\Exceptions\HookExecutionException;
use PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException;

trait HasLifeCycleHooks
{
    /**
     * @var Collection<int, LifeCycleHook>
     */
    protected Collection $hooks;
    
    /**
     * @param Collection<int, LifeCycleHook> $hooks
     */
    public function setHooks(Collection $hooks): void
    {
        $this->hooks = $hooks;
    }
    
    /**
     * @return Collection<int, LifeCycleHook>
     */
    public function getHooks(): Collection
    {
        return $this->hooks ?? collect();
    }
    
    /**
     * Run hooks with spread operator
     * 
     * @param string $lifeCycle
     * @param mixed ...$args Individual arguments passed by reference
     * @throws InvalidLifeCycleException
     * @throws HookExecutionException
     */
    public function runHook(string $lifeCycle, &...$args): void
    {
        if (!array_key_exists($lifeCycle, static::lifeCycle())) {
            throw new InvalidLifeCycleException(
                "LifeCycle '{$lifeCycle}' is not defined in " . static::class
            );
        }
        
        $expectedArgs = static::lifeCycle()[$lifeCycle];
        
        // Validate we have all required arguments
        if (count($args) < count($expectedArgs)) {
            $missing = array_slice($expectedArgs, count($args));
            throw new InvalidLifeCycleException(
                "LifeCycle '{$lifeCycle}' expects arguments: " . implode(', ', $missing)
            );
        }
        
        // Create associative array with references for hooks
        $argsArray = [];
        foreach ($expectedArgs as $index => $argName) {
            $argsArray[$argName] = &$args[$index];
        }
        
        // Run hooks with the args array
        $this->getHooks()
            ->filter(fn(LifeCycleHook $hook) => $hook->getLifeCycle() === $lifeCycle)
            ->each(function (LifeCycleHook $hook) use (&$argsArray, $lifeCycle) {
                try {
                    $hook->handle($argsArray);
                } catch (\Throwable $e) {
                    self::handlerError($hook, $e, $lifeCycle);
                }
            });
    }
    
    public function addHook(LifeCycleHook $hook): void
    {
        if (!isset($this->hooks)) {
            $this->hooks = collect();
        }
        
        $this->hooks->push($hook);
    }
    
    public function removeHooksFor(string $lifeCycle): void
    {
        $this->hooks = $this->getHooks()
            ->reject(fn(LifeCycleHook $hook) => $hook->getLifeCycle() === $lifeCycle);
    }

    private static function handlerError(LifeCycleHook $hook, \Throwable $e, string $lifeCycle): void
    {
        if ($hook->getSeverity() === 'critical') {
            throw new HookExecutionException(
                "Critical hook failed in lifecycle '{$lifeCycle}': " . $e->getMessage(),
                previous: $e
            );
        }
        
        if (function_exists('logger') && app()->bound('log')) {
            logger()->error("Hook failed in lifecycle '{$lifeCycle}'", [
                'hook' => get_class($hook),
                'severity' => $hook->getSeverity(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}