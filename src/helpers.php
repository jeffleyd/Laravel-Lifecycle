<?php

use PhpDiffused\Lifecycle\LifeCycleManager;
use Illuminate\Container\Container;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     */
    function app(string $abstract = null)
    {
        $container = Container::getInstance();
        
        if (is_null($abstract)) {
            return $container;
        }
        
        return $container->make($abstract);
    }
}

if (!function_exists('logger')) {
    /**
     * Get a logger instance for testing.
     */
    function logger()
    {
        return new class {
            public function info($message, $context = []) {
                // Mock logger for tests
            }
            public function debug($message, $context = []) {
                // Mock logger for tests
            }
            public function error($message, $context = []) {
                // Mock logger for tests
            }
            public function warning($message, $context = []) {
                // Mock logger for tests
            }
        };
    }
}

if (!function_exists('runHook')) {
    /**
     * Run lifecycle hooks for a class or instance
     * 
     * @param string|object $target Class name (e.g., Payment::class) or instance (e.g., $this or self)
     * @param string $lifeCycle The lifecycle event name (e.g., 'payment.begin')
     * @param mixed ...$args Arguments to pass to the hooks (passed by reference)
     * 
     * @example Inside a class:
     *   runHook(self::class, 'payment.begin', $total, $discount);
     *   runHook($this, 'payment.begin', $total, $discount);
     * 
     * @example Outside a class:
     *   runHook(Payment::class, 'payment.begin', $total, $discount);
     *   runHook($paymentInstance, 'payment.begin', $total, $discount);
     * 
     * @throws \PhpDiffused\Lifecycle\Exceptions\InvalidLifeCycleException
     * @throws \PhpDiffused\Lifecycle\Exceptions\HookExecutionException
     */
    function runHook($target, string $lifeCycle, &...$args): void
    {
        $manager = app(LifeCycleManager::class);
        $manager->runHook($target, $lifeCycle, ...$args);
    }
}

if (!function_exists('addHook')) {
    /**
     * Add a hook dynamically to a class
     * 
     * NOTE: In the new kernel-based system, hooks should be registered in the Kernel.
     * This function is maintained for compatibility but will log a warning.
     * 
     * @param string|object $target Class name or instance
     * @param object $hook Hook instance
     */
    function addHook($target, $hook): void
    {
        $className = is_object($target) ? get_class($target) : $target;

        $manager = app(LifeCycleManager::class);
        $manager->addHook($className, $hook);
    }
}

if (!function_exists('removeHooksFor')) {
    /**
     * Remove all hooks for a specific lifecycle in a class
     * 
     * NOTE: In the new kernel-based system, hooks should be managed in the Kernel.
     * This function is maintained for compatibility but will log a warning.
     * 
     * @param string|object $target Class name or instance
     * @param string $lifeCycle
     */
    function removeHooksFor($target, string $lifeCycle): void
    {
        $className = is_object($target) ? get_class($target) : $target;

        $manager = app(LifeCycleManager::class);
        $manager->removeHooksFor($className, $lifeCycle);
    }
}