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

        // Registrar apenas o KernelBasedLifeCycleManager
        $this->app->singleton(LifeCycleManager::class, function ($app) {
            return new LifeCycleManager();
        });

        // Manter compatibilidade com o LifeCycleManager antigo se necessário
        $this->app->singleton(LifeCycleManager::class, function ($app) {
            // Retornar o novo manager para manter compatibilidade
            return $app->make(LifeCycleManager::class);
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
            
            // Comando para limpar cache
            $this->commands([
                \PhpDiffused\Lifecycle\Console\ClearHooksCacheCommand::class,
            ]);
        }
    }
}
