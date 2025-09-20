<?php

namespace PhpDiffused\Lifecycle\Testing;

use PHPUnit\Framework\Assert;

class HookSpy
{
    protected string $hookClass;
    protected $testContext;
    protected array $executions = [];

    public function __construct(string $hookClass, $testContext)
    {
        $this->hookClass = $hookClass;
        $this->testContext = $testContext;
    }

    public function recordExecution(array $args): void
    {
        $this->executions[] = $args;
    }

    public function assertExecuted(): self
    {
        Assert::assertGreaterThan(
            0,
            count($this->executions),
            "Expected {$this->hookClass} to be executed at least once"
        );
        return $this;
    }

    public function assertExecutedTimes(int $times): self
    {
        Assert::assertEquals(
            $times,
            count($this->executions),
            "Expected {$this->hookClass} to be executed {$times} times, but was executed " . count($this->executions) . " times"
        );
        return $this;
    }

    public function assertReceivedArgs(array $expectedArgs): self
    {
        Assert::assertNotEmpty($this->executions, "Hook {$this->hookClass} was not executed");

        $lastExecution = end($this->executions);
        foreach ($expectedArgs as $key => $value) {
            Assert::assertArrayHasKey($key, $lastExecution);
            Assert::assertEquals($value, $lastExecution[$key]);
        }

        return $this;
    }
}