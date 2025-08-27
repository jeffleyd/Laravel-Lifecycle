<?php

namespace PhpDiffused\Lifecycle;

use Illuminate\Support\ServiceProvider;

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
            // Publicar apenas o Kernel
            $this->publishes([
                __DIR__.'/../stubs/Kernel.stub' => app_path('Hooks/Kernel.php'),
            ], 'lifecycle-kernel');
            
            // Publicar configuração
            $this->publishes([
                __DIR__.'/../config/lifecycle.php' => config_path('lifecycle.php'),
            ], 'lifecycle-config');
            
            // Comandos
            $this->commands([
                \PhpDiffused\Lifecycle\Console\MakeLifecycleCommand::class,
                \PhpDiffused\Lifecycle\Console\MakeHookCommand::class,
                \PhpDiffused\Lifecycle\Console\AnalyzeLifecycleCommand::class,
            ]);
        }
    }
}
