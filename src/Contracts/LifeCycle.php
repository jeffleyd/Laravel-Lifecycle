<?php

namespace PhpDiffused\Lifecycle\Contracts;

interface LifeCycle
{
    /**
     * @return array<string, array<string>>
     */
    public static function lifeCycle(): array;
}