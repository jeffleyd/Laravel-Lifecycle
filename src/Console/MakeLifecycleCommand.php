<?php

namespace PhpDiffused\Lifecycle\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class MakeLifecycleCommand extends GeneratorCommand
{
    protected $name = 'lifecycle:main';
    protected $description = 'Create a new lifecycle service class';
    protected $type = 'Lifecycle Service';

    protected function getStub()
    {
        return __DIR__.'/../../stubs/LifecycleService.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the lifecycle service class'],
        ];
    }

    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name).'\\', '', $name);
        
        return str_replace(['DummyClass', '{{ class }}', '{{class}}'], $class, $stub);
    }
}
