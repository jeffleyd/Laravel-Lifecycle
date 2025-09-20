<?php

namespace PhpDiffused\Lifecycle\Testing;

class HookReplacer
{
    protected string $hookClass;
    protected $testContext;
    protected $handler;

    public function __construct(string $hookClass, $testContext)
    {
        $this->hookClass = $hookClass;
        $this->testContext = $testContext;
    }

    public function with(callable $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    public function getReplacementHook(): object
    {
        return new class($this->handler) {
            protected $handler;

            public function __construct($handler)
            {
                $this->handler = $handler;
            }

            public function handle(array &$args): void
            {
                if ($this->handler) {
                    ($this->handler)($args);
                }
            }

            public function getSeverity(): string
            {
                return 'optional';
            }
        };
    }
}