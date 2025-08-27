<?php

namespace PhpDiffused\Lifecycle\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeHookCommand extends GeneratorCommand
{
    protected $name = 'lifecycle:hook';
    protected $description = 'Create a new lifecycle hook class';
    protected $type = 'Hook';

    protected function getStub()
    {
        return __DIR__.'/../../stubs/Hook.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Hooks';
    }

    protected function getPath($name)
    {
        $name = str_replace('\\', '/', $name);
        
        // Forçar que sempre salve na pasta app/Hooks
        $hookPath = app_path('Hooks/'.class_basename($name).'.php');
        
        return $hookPath;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the hook class'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['scope', null, InputOption::VALUE_OPTIONAL, 'The scope for the hook', 'default'],
            ['point', null, InputOption::VALUE_OPTIONAL, 'The lifecycle point for the hook', 'before_action'],
            ['severity', null, InputOption::VALUE_OPTIONAL, 'The severity of the hook (Critical|Optional)', 'Optional'],
        ];
    }

    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name).'\\', '', $name);
        $scope = $this->option('scope');
        $point = $this->option('point');
        $severity = $this->option('severity');
        
        $stub = str_replace(['DummyClass', '{{ class }}', '{{class}}'], $class, $stub);
        $stub = str_replace(['DummyScope', '{{ scope }}', '{{scope}}'], $scope, $stub);
        $stub = str_replace(['DummyPoint', '{{ point }}', '{{point}}'], $point, $stub);
        $stub = str_replace(['DummySeverity', '{{ severity }}', '{{severity}}'], $severity, $stub);
        
        return $stub;
    }

    protected function makeDirectory($path)
    {
        // Criar diretório app/Hooks se não existir
        $hooksDir = app_path('Hooks');
        if (!is_dir($hooksDir)) {
            mkdir($hooksDir, 0755, true);
        }
        
        return parent::makeDirectory($path);
    }
}
