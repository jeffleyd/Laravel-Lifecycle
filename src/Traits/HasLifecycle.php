<?php

namespace PhpDiffused\Lifecycle\Traits;

use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use ReflectionClass;

trait HasLifecycle
{
    public static function lifeCycle(): array
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(LifeCyclePoint::class);
        
        $lifeCycles = [];
        foreach ($attributes as $attribute) {
            $lifeCyclePoint = $attribute->newInstance();
            $lifeCycles[$lifeCyclePoint->name] = $lifeCyclePoint->parameters;
        }
        
        return $lifeCycles;
    }

    protected function runLifeCycleHook(string $lifeCycle, &...$args): void
    {
        $manager = app(\PhpDiffused\Lifecycle\LifeCycleManager::class);
        $manager->runHook(static::class, $lifeCycle, ...$args);
    }
}
