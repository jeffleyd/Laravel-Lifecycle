<?php

namespace PhpDiffused\Lifecycle\Attributes;

enum Severity: string
{
    case Critical = 'critical';
    case Optional = 'optional';
}
