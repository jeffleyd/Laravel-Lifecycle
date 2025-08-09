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
     * @var \App\Hooks\HooksKernel|null
     */
    public $hooksKernel = null;
    
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/lifecycle.php', 'lifecycle'
        );
        
        if (class_exists(\App\Hooks\HooksKernel::class)) {
            $this->hooksKernel = new \App\Hooks\HooksKernel();
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

        $availableHooks = array_merge(
            $this->discoverHooksInPath($hookPath, $className),
            $this->discoverHooksInLifecycleFolders($hookPath, $className, $class)
        );
        
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
     * Discover hooks in lifecycle-specific folders (new structure)
     * 
     * @param string $hookPath
     * @param string $className
     * @param string $fullClassName
     * @return array
     */
    protected function discoverHooksInLifecycleFolders(string $hookPath, string $className, string $fullClassName): array
    {
        $availableHooks = [];
        
        if (!File::isDirectory($hookPath)) {
            return $availableHooks;
        }

        if (!is_subclass_of($fullClassName, \PhpDiffused\Lifecycle\Contracts\LifeCycle::class)) {
            return $availableHooks;
        }
        
        $lifecycles = $fullClassName::lifeCycle();
        
        foreach (array_keys($lifecycles) as $lifecycle) {
            $lifecycleFolders = $this->getLifecycleFolderNames($lifecycle);
            
            foreach ($lifecycleFolders as $folderName) {
                $lifecyclePath = $hookPath . DIRECTORY_SEPARATOR . $folderName;
                
                if (File::isDirectory($lifecyclePath)) {
                    $files = File::allFiles($lifecyclePath);
                    
                    foreach ($files as $file) {
                        if ($file->getExtension() !== 'php') {
                            continue;
                        }
                        
                        $relativePath = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
                        $hookClass = "App\\Hooks\\{$className}\\{$relativePath}";
                        
                        if (class_exists($hookClass) && is_subclass_of($hookClass, LifeCycleHook::class)) {
                            $availableHooks[$hookClass] = $this->app->make($hookClass);
                        }
                    }
                }
            }
        }
        
        return $availableHooks;
    }
    
    /**
     * Convert lifecycle name to possible folder names
     * 
     * @param string $lifecycle
     * @return array
     */
    protected function getLifecycleFolderNames(string $lifecycle): array
    {
        $folderNames = [];

        if (strpos($lifecycle, '.') !== false) {
            $folderNames[] = str_replace('.', '', ucwords($lifecycle, '.'));
            $folderNames[] = str_replace('.', '_', ucwords($lifecycle, '.'));
            $folderNames[] = str_replace('.', '_', $lifecycle);
        }

        if (strpos($lifecycle, '_') !== false) {
            $folderNames[] = str_replace('_', '', ucwords($lifecycle, '_'));
            $folderNames[] = ucwords($lifecycle, '_');
            $folderNames[] = $lifecycle;
        }

        if (preg_match('/[a-z][A-Z]/', $lifecycle)) {
            $snakeCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $lifecycle));
            $folderNames[] = $snakeCase;
            $folderNames[] = ucwords($snakeCase, '_');
            $folderNames[] = $lifecycle;
        }

        if (!strpos($lifecycle, '.') && !strpos($lifecycle, '_') && !preg_match('/[a-z][A-Z]/', $lifecycle)) {
            $folderNames[] = $lifecycle;
        }

        $folderNames = array_filter($folderNames, function($name) {
            return strpos($name, '.') === false;
        });
        
        return array_unique($folderNames);
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