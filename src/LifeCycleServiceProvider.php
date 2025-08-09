<?php

namespace PhpDiffused\Lifecycle;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;

class LifeCycleServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<class-string<LifeCycleHook>>>
     */
    protected array $hookCache = [];
    
    /**
     * @var \App\Hooks\Kernel|null
     */
    public $hooksKernel = null;
    
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/lifecycle.php', 'lifecycle'
        );
        
        if (class_exists(\App\Hooks\Kernel::class)) {
            $this->hooksKernel = new \App\Hooks\Kernel();
        }

        $this->app->singleton(LifeCycleManager::class, function ($app) {
            return new LifeCycleManager($this);
        });

        $this->app->afterResolving(LifeCycle::class, function (LifeCycle $instance) {
            if (method_exists($instance, 'setHooks')) {
                $hooks = $this->resolveHooksFor(get_class($instance));
                $instance->setHooks($hooks);
            }
        });
    }
    
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {            
            $this->publishes([
                __DIR__.'/../stubs/Kernel.stub' => app_path('Hooks/Kernel.php'),
            ], 'lifecycle-kernel');
            
            $this->publishes([
                __DIR__.'/../config/lifecycle.php' => config_path('lifecycle.php'),
            ], 'lifecycle-config');
            
            // Clear cache command
            $this->commands([
                \PhpDiffused\Lifecycle\Console\ClearHooksCacheCommand::class,
            ]);
        }
    }
    
    public function resolveHooksFor(string $class): \Illuminate\Support\Collection
    {
        if (config('lifecycle.cache.enabled')) {
            $cacheKey = config('lifecycle.cache.key') . '.' . $class;
            $cacheTtl = config('lifecycle.cache.ttl', 86400);
            
            return Cache::remember($cacheKey, $cacheTtl, function () use ($class) {
                return $this->discoverHooksFor($class);
            });
        }

        if (isset($this->hookCache[$class])) {
            return collect($this->hookCache[$class])
                ->map(fn($hookClass) => $this->app->make($hookClass));
        }
        
        return $this->discoverHooksFor($class);
    }
    
    protected function discoverHooksFor(string $class): \Illuminate\Support\Collection
    {
        $hooks = collect();

        if (!config('lifecycle.auto_discovery', true)) {
            return $hooks;
        }
        
        $className = class_basename($class);
        $discoveryPath = config('lifecycle.discovery_path', app_path('Hooks'));
        $hookPath = $discoveryPath . DIRECTORY_SEPARATOR . $className;

        $availableHooks = $this->discoverHooksInPath($hookPath, $className);
        
        if (!$this->hooksKernel || empty($availableHooks)) {
            foreach ($availableHooks as $hookClass => $hookInstance) {
                $hooks->push($hookInstance);
                $this->hookCache[$class][] = $hookClass;
            }
            return $hooks;
        }
        
        $orderedHooks = $this->organizeHooksByKernel($class, $availableHooks);
        
        foreach ($orderedHooks as $hookClass => $hookInstance) {
            $hooks->push($hookInstance);
            $this->hookCache[$class][] = $hookClass;
        }
        
        return $hooks;
    }
    
    /**
     * Discover hooks in the root path (old structure)
     * 
     * @param string $hookPath
     * @param string $className
     * @return array
     */
    protected function discoverHooksInPath(string $hookPath, string $className): array
    {
        $availableHooks = [];
        
        if (!File::isDirectory($hookPath)) {
            return $availableHooks;
        }
        
        $files = File::files($hookPath);
        
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            
            $hookClassName = $file->getBasename('.php');
            $hookClass = "App\\Hooks\\{$className}\\{$hookClassName}";
            
            if (class_exists($hookClass) && is_subclass_of($hookClass, LifeCycleHook::class)) {
                $availableHooks[$hookClass] = $this->app->make($hookClass);
            }
        }
        
        return $availableHooks;
    }
    

    
    /**
     * @param string $serviceClass
     * @param array<class-string, LifeCycleHook> $availableHooks
     * @return array<class-string, LifeCycleHook>
     */
    protected function organizeHooksByKernel(string $serviceClass, array $availableHooks): array
    {
        $orderedHooks = [];
        $kernelHooks = $this->hooksKernel->hooks;
        
        if (!isset($kernelHooks[$serviceClass])) {
            return $availableHooks;
        }
        
        $hooksByLifecycle = [];
        foreach ($availableHooks as $hookClass => $hookInstance) {
            $lifecycle = $hookInstance->getLifeCycle();
            if (!isset($hooksByLifecycle[$lifecycle])) {
                $hooksByLifecycle[$lifecycle] = [];
            }
            $hooksByLifecycle[$lifecycle][$hookClass] = $hookInstance;
        }
        
        foreach ($hooksByLifecycle as $lifecycle => $lifecycleHooks) {
            $order = $kernelHooks[$serviceClass][$lifecycle] ?? [];
            
            foreach ($order as $orderedHookClass) {
                if (isset($lifecycleHooks[$orderedHookClass])) {
                    $orderedHooks[$orderedHookClass] = $lifecycleHooks[$orderedHookClass];
                    unset($lifecycleHooks[$orderedHookClass]);
                }
            }
            
            foreach ($lifecycleHooks as $hookClass => $hookInstance) {
                $orderedHooks[$hookClass] = $hookInstance;
            }
        }
        
        return $orderedHooks;
    }
}