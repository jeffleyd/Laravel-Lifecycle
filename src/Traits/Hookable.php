<?php

namespace PhpDiffused\Lifecycle\Traits;

use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use ReflectionClass;

trait Hookable
{
    abstract public function handle(array &$args): void;

    public function getHookInfo(): ?Hook
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(Hook::class);
        
        return $attributes ? $attributes[0]->newInstance() : null;
    }

    public function getLifeCycle(): string
    {
        $hookInfo = $this->getHookInfo();
        return $hookInfo ? $hookInfo->point : '';
    }

    public function getScope(): string
    {
        $hookInfo = $this->getHookInfo();
        return $hookInfo ? $hookInfo->scope : '';
    }

    public function getSeverity(): string
    {
        $hookInfo = $this->getHookInfo();
        return $hookInfo ? $hookInfo->severity->value : Severity::Optional->value;
    }
}
