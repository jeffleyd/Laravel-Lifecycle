<?php

namespace PhpDiffused\Lifecycle\Testing;

class HookMocker
{
    protected string $hookClass;
    protected $testContext;
    public bool $shouldFail = false;
    public ?\Throwable $failureException = null;
    protected $customHandler = null;

    public function __construct(string $hookClass, $testContext)
    {
        $this->hookClass = $hookClass;
        $this->testContext = $testContext;
    }

    public function shouldFail(\Throwable $exception): self
    {
        $this->shouldFail = true;
        $this->failureException = $exception;
        return $this;
    }

    public function shouldReceive(string $method): self
    {
        // For compatibility with Mockery-style expectations
        return $this;
    }

    public function with(callable $handler): self
    {
        $this->customHandler = $handler;
        return $this;
    }

    public function getReplacementHook(): object
    {
        $hookClass = $this->hookClass;
        return new class($this->customHandler, $this->shouldFail, $this->failureException, $hookClass) {
            protected $handler;
            protected bool $shouldFail;
            protected ?\Throwable $exception;
            protected string $hookClass;

            public function __construct($handler, bool $shouldFail, ?\Throwable $exception, string $hookClass)
            {
                $this->handler = $handler;
                $this->shouldFail = $shouldFail;
                $this->exception = $exception;
                $this->hookClass = $hookClass;
            }

            public function handle(array &$args): void
            {
                if ($this->shouldFail) {
                    throw $this->exception;
                }

                if ($this->handler) {
                    ($this->handler)($args);
                }
            }

            public function getSeverity(): string
            {
                // Try to get severity from original hook class
                if (class_exists($this->hookClass)) {
                    try {
                        $instance = new $this->hookClass();
                        if (method_exists($instance, 'getSeverity')) {
                            return $instance->getSeverity();
                        }
                    } catch (\Throwable $e) {
                        // Ignore errors creating instance
                    }
                }
                return 'optional';
            }
        };
    }
}