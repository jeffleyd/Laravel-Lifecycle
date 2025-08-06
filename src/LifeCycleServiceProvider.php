<?php

namespace PhpDiffused\Lifecycle;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use PhpDiffused\Lifecycle\Contracts\LifeCycle;
use PhpDiffused\Lifecycle\Contracts\LifeCycleHook;
use ReflectionClass;

class LifeCycleServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<class-string<LifeCycleHook>>>
     */
    protected array $hookCache = [];
    
    /**
     * @var \App\Hooks\HooksKernel|null
     */
    protected $hooksKernel = null;
    
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/lifecycle.php', 'lifecycle'
        );
        
        if (class_exists(\App\Hooks\HooksKernel::class)) {
            $this->hooksKernel = new \App\Hooks\HooksKernel();
        }
        
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
                __DIR__.'/../stubs/HooksKernel.stub' => app_path('Hooks/HooksKernel.php'),
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
    
    protected function resolveHooksFor(string $class): \Illuminate\Support\Collection
    {
        // Check if cache is enabled
        if (config('lifecycle.cache.enabled')) {
            $cacheKey = config('lifecycle.cache.key') . '.' . $class;
            $cacheTtl = config('lifecycle.cache.ttl', 86400);
            
            return Cache::remember($cacheKey, $cacheTtl, function () use ($class) {
                return $this->discoverHooksFor($class);
            });
        }
        
        // Check memory cache
        if (isset($this->hookCache[$class])) {
            return collect($this->hookCache[$class])
                ->map(fn($hookClass) => $this->app->make($hookClass));
        }
        
        return $this->discoverHooksFor($class);
    }
    
    protected function discoverHooksFor(string $class): \Illuminate\Support\Collection
    {
        $hooks = collect();
        
        // Check if auto-discovery is enabled
        if (!config('lifecycle.auto_discovery', true)) {
            return $hooks;
        }
        
        $className = class_basename($class);
        $discoveryPath = config('lifecycle.discovery_path', app_path('Hooks'));
        $hookPath = $discoveryPath . DIRECTORY_SEPARATOR . $className;
        
        $availableHooks = [];
        if (File::isDirectory($hookPath)) {
            $files = File::allFiles($hookPath);
            
            foreach ($files as $file) {
                $relativePath = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
                $hookClass = "App\\Hooks\\{$className}\\{$relativePath}";
                
                if (class_exists($hookClass) && is_subclass_of($hookClass, LifeCycleHook::class)) {
                    $availableHooks[$hookClass] = $this->app->make($hookClass);
                }
            }
        }
        
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
     * @param string $serviceClass
     * @param array<class-string, LifeCycleHook> $availableHooks
     * @return array<class-string, LifeCycleHook>
     */
    protected function organizeHooksByKernel(string $serviceClass, array $availableHooks): array
    {
        $orderedHooks = [];
        $kernelOrder = $this->hooksKernel->hookOrder;
        
        if (!isset($kernelOrder[$serviceClass])) {
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
            $order = $kernelOrder[$serviceClass][$lifecycle] ?? [];
            
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