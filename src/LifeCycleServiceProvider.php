<?php

namespace PhpDiffused\Lifecycle;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
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
                __DIR__.'/../config/lifecycle.php' => config_path('lifecycle.php'),
            ], 'lifecycle-config');
            
            $this->publishes([
                __DIR__.'/../stubs/HooksKernel.stub' => app_path('Hooks/HooksKernel.php'),
            ], 'lifecycle-kernel');
        }
    }
    
    protected function resolveHooksFor(string $class): \Illuminate\Support\Collection
    {
        if (isset($this->hookCache[$class])) {
            return collect($this->hookCache[$class])
                ->map(fn($hookClass) => $this->app->make($hookClass));
        }
        
        $hooks = collect();
        $className = class_basename($class);
        $hookPath = app_path("Hooks/{$className}");
        
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