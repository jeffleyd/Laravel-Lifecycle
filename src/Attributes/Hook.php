<?php

namespace PhpDiffused\Lifecycle\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Hook
{
    public function __construct(
        public readonly string $scope,
        public readonly string $point,
        public readonly Severity $severity = Severity::Optional
    ) {}
}
