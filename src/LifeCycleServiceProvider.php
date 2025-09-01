<?php

namespace PhpDiffused\Lifecycle;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\App;

class LifeCycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/lifecycle.php', 'lifecycle'
        );

        $this->app->singleton(LifeCycleManager::class, function ($app) {
            return new LifeCycleManager();
        });
    }
    
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $hooksDir = $this->app->basePath('app/Hooks');
            if (!is_dir($hooksDir)) {
                mkdir($hooksDir, 0755, true);
            }
            
            $this->publishes([
                __DIR__.'/../stubs/Kernel.stub' => $this->app->basePath('app/Hooks/Kernel.php'),
                __DIR__.'/../config/lifecycle.php' => $this->app->configPath('lifecycle.php'),
            ], 'laravel-assets');
            
            $this->publishes([
                __DIR__.'/../stubs/Kernel.stub' => $this->app->basePath('app/Hooks/Kernel.php'),
            ], 'lifecycle-kernel');

            $this->publishes([
                __DIR__.'/../config/lifecycle.php' => $this->app->configPath('lifecycle.php'),
            ], 'lifecycle-config');

            $this->commands([
                \PhpDiffused\Lifecycle\Console\MakeLifecycleCommand::class,
                \PhpDiffused\Lifecycle\Console\MakeHookCommand::class,
                \PhpDiffused\Lifecycle\Console\AnalyzeLifecycleCommand::class,
            ]);
        }
    }
}
