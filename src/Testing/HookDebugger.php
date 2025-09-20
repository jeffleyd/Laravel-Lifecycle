<?php

namespace PhpDiffused\Lifecycle\Testing;

class HookDebugger
{
    protected $testContext;
    protected bool $dumpOnFailure = false;
    protected bool $logPath = false;

    /**
     * @throws \ReflectionException
     */
    public function __construct($testContext)
    {
        $this->testContext = $testContext;

        $reflection = new \ReflectionClass($testContext);
        
        $captureSequenceProp = $reflection->getProperty('captureSequence');
        $captureSequenceProp->setValue($testContext, true);
        
        $captureMutationsProp = $reflection->getProperty('captureMutations');
        $captureMutationsProp->setValue($testContext, true);
        
        $captureMetricsProp = $reflection->getProperty('captureMetrics');
        $captureMetricsProp->setValue($testContext, true);
    }

    public function dumpOnFailure(): self
    {
        $this->dumpOnFailure = true;
        return $this;
    }

    public function logExecutionPath(): self
    {
        $this->logPath = true;
        return $this;
    }

    public function __destruct()
    {
        if ($this->logPath) {
            $this->printExecutionPath();
        }
    }

    protected function printExecutionPath(): void
    {
        echo "\n=== Hook Execution Path ===\n";

        $reflection = new \ReflectionClass($this->testContext);
        $executionSequenceProp = $reflection->getProperty('executionSequence');
        $executionSequence = $executionSequenceProp->getValue($this->testContext);
        
        foreach ($executionSequence as $item) {
            echo sprintf(
                "[%.4f] %s -> %s\n",
                $item['timestamp'],
                $item['lifecycle'],
                $item['hook']
            );
        }
        echo "===========================\n";
    }
}