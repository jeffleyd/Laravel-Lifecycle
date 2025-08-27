<?php

namespace PhpDiffused\Lifecycle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class LifeCyclePoint
{
    public function __construct(
        public readonly string $name,
        public readonly array $parameters = []
    ) {}
}
