<?php

namespace PhpDiffused\Lifecycle\Testing;

class HookScenarioBuilder
{
    protected string $scenario;
    protected $testContext;
    protected array $conditions = [];
    protected array $expectations = [];

    public function __construct(string $scenario, $testContext)
    {
        $this->scenario = $scenario;
        $this->testContext = $testContext;
    }

    public function whereAmount(string $condition): self
    {
        $this->conditions['amount'] = $condition;
        return $this;
    }

    public function withUser(array $attributes): self
    {
        $this->conditions['user'] = $attributes;
        return $this;
    }

    public function expectHooks(array $hooks): self
    {
        $this->expectations = $hooks;
        $this->applyScenario();
        return $this;
    }

    protected function applyScenario(): void
    {
        foreach ($this->expectations as $hookClass => $behavior) {
            if ($behavior === 'strict_mode') {
                $this->testContext->spyHook($hookClass);
            } elseif ($behavior === 'required') {
                // Mark as critical for this test
            } elseif ($behavior === 'immediate') {
                // Ensure executes first
            }
        }
    }
}