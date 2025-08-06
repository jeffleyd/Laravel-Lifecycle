<?php

namespace PhpDiffused\Lifecycle\Contracts;

interface LifeCycleHook
{
    /**
     * @param array<string, mixed> $args
     */
    public function handle(array &$args): void;

    public function getLifeCycle(): string;

    public function getSeverity(): string;
}