<?php

namespace PhpDiffused\Lifecycle\Console;

use Illuminate\Console\Command;
use PhpDiffused\Lifecycle\LifeCycleManager;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use Symfony\Component\Console\Input\InputArgument;
use ReflectionClass;

class AnalyzeLifecycleCommand extends Command
{
    protected $signature = 'lifecycle:analyze {class : The class to analyze}';
    protected $description = 'Analyze lifecycle points and hooks for a given class';

    public function handle(LifeCycleManager $manager)
    {
        $className = $this->argument('class');
        $className = str_replace('/', '\\', $className);

        if (!class_exists($className)) {
            $this->error("Class {$className} does not exist.");
            return 1;
        }

        $this->info("Analyzing lifecycle for: {$className}");
        $this->line('');

        try {
            $lifecyclePoints = $this->getLifecyclePoints($className);
            
            if (empty($lifecyclePoints)) {
                $this->warn("No lifecycle points found in {$className}");
                return 0;
            }

            $this->displayLifecyclePoints($lifecyclePoints);
            $this->line('');
            $this->analyzeHooks($className, $lifecyclePoints, $manager);
        $this->line('');
        $this->analyzeSideEffects($className, $lifecyclePoints, $manager);
            
        } catch (\Exception $e) {
            $this->error("Error analyzing class: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function getLifecyclePoints(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(LifeCyclePoint::class);
        
        $lifecyclePoints = [];
        
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $lifecyclePoints[] = [
                'point' => $instance->name,
                'arguments' => $instance->parameters
            ];
        }

        return $lifecyclePoints;
    }

    private function displayLifecyclePoints(array $lifecyclePoints): void
    {
        $this->info("Lifecycle Points (" . count($lifecyclePoints) . "):");
        
        foreach ($lifecyclePoints as $point) {
            $arguments = implode(', ', $point['arguments']);
            $this->line("  • {$point['point']}: [{$arguments}]");
        }
    }

    private function analyzeHooks(string $className, array $lifecyclePoints, LifeCycleManager $manager): void
    {
        $this->info("Hook Analysis:");
        
        $kernelHooks = $this->getKernelHooks();
        $registeredHooks = $manager->getHooksFor($className);
        
        $totalHooks = 0;
        $totalCritical = 0;
        $totalOptional = 0;

        foreach ($lifecyclePoints as $point) {
            $pointName = $point['point'];
            $hooks = $this->getHooksForLifecycle($kernelHooks, $registeredHooks, $pointName, $className);
            
            $critical = 0;
            $optional = 0;
            
            foreach ($hooks as $hookClass) {
                if (class_exists($hookClass)) {
                    try {
                        $hookInstance = new $hookClass();
                        if (method_exists($hookInstance, 'getSeverity')) {
                            $severity = $hookInstance->getSeverity();
                            if ($severity === 'critical') {
                                $critical++;
                                $totalCritical++;
                            } else {
                                $optional++;
                                $totalOptional++;
                            }
                        } else {
                            $optional++;
                            $totalOptional++;
                        }
                    } catch (\Exception $e) {
                        $optional++;
                        $totalOptional++;
                    }
                } else {
                    $optional++;
                    $totalOptional++;
                }
            }
            
            $totalHooks += count($hooks);
            
            $this->line("  • {$pointName}: " . count($hooks) . " hooks ({$critical} critical, {$optional} optional)");
            
            if (!empty($hooks)) {
                foreach ($hooks as $hookClass) {
                    $severity = 'optional';
                    $scope = 'unknown';
                    
                    if (class_exists($hookClass)) {
                        try {
                            $hookInstance = new $hookClass();
                            $severity = method_exists($hookInstance, 'getSeverity') ? $hookInstance->getSeverity() : 'optional';
                            $scope = method_exists($hookInstance, 'getScope') ? $hookInstance->getScope() : 'unknown';
                        } catch (\Exception $e) {
                            // Use defaults
                        }
                    }
                    
                    $this->line("    - {$hookClass} [{$severity}] (scope: {$scope})");
                }
            }
        }

        $this->line('');
        $this->info("Summary:");
        $this->line("  • Total hooks: {$totalHooks}");
        $this->line("  • Critical hooks: {$totalCritical}");
        $this->line("  • Optional hooks: {$totalOptional}");
        
        if ($totalCritical > 0) {
            $this->line('');
            $this->warn("⚠️  This class has {$totalCritical} critical hooks that will throw exceptions on failure.");
        }
    }

    private function getKernelHooks(): array
    {
        $kernelClass = 'App\\Hooks\\Kernel';
        
        if (!class_exists($kernelClass)) {
            return [];
        }

        try {
            $kernel = new $kernelClass();
            if (property_exists($kernel, 'hooks')) {
                return $kernel->hooks;
            }
        } catch (\Exception $e) {
            // Kernel not available
        }

        return [];
    }

    private function getHooksForLifecycle($kernelHooks, $registeredHooks, string $lifecycle, string $targetClass): array
    {
        $hooks = [];

        if (isset($kernelHooks[$targetClass][$lifecycle])) {
            $hooks = $kernelHooks[$targetClass][$lifecycle];
        }

        return $hooks;
    }

    private function analyzeSideEffects(string $className, array $lifecyclePoints, LifeCycleManager $manager): void
    {
        $this->info("Side Effects Analysis:");
        
        $kernelHooks = $this->getKernelHooks();
        $registeredHooks = $manager->getHooksFor($className);
        
        $potentialIssues = [];
        $hooksWithSideEffects = [];
        
        foreach ($lifecyclePoints as $point) {
            $pointName = $point['point'];
            $hooks = $this->getHooksForLifecycle($kernelHooks, $registeredHooks, $pointName, $className);
            $parameters = $point['arguments'];
            
            if (count($hooks) > 1) {
                $modifyingHooks = $this->analyzeHooksForModifications($hooks, $parameters);
                
                if (!empty($modifyingHooks)) {
                    $hooksWithSideEffects[$pointName] = $modifyingHooks;
                    $conflicts = $this->detectParameterConflicts($modifyingHooks);
                    
                    if (!empty($conflicts)) {
                        $potentialIssues[$pointName] = $conflicts;
                    }
                }
            }
        }
        
        $this->displaySideEffects($hooksWithSideEffects);
        $this->displayPotentialIssues($potentialIssues);
    }
    
    private function analyzeHooksForModifications(array $hooks, array $parameters): array
    {
        $modifyingHooks = [];
        
        foreach ($hooks as $hookClass) {
            $suspectedModifications = $this->detectPotentialModifications($hookClass, $parameters);
            
            if (!empty($suspectedModifications)) {
                $modifyingHooks[$hookClass] = $suspectedModifications;
            }
        }
        
        return $modifyingHooks;
    }
    
    private function detectPotentialModifications(string $hookClass, array $parameters): array
    {
        $modifications = [];
        
        try {
            $reflection = new ReflectionClass($hookClass);
            
            if ($reflection->hasMethod('handle')) {
                $handleMethod = $reflection->getMethod('handle');
                $source = $this->getMethodSource($handleMethod);
                

                foreach ($parameters as $index => $param) {
                    if ($this->sourceModifiesParameter($source, $param, $index)) {
                        $modifications[] = $param;
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Unable to analyze source code
        }
        
        return $modifications;
    }
    
    private function getMethodSource(\ReflectionMethod $method): string
    {
        try {
            $filename = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();
            
            if ($filename && $startLine && $endLine) {
                $source = file($filename);
                return implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));
            }
        } catch (\Exception $e) {
            // Unable to read file
        }
        
        return '';
    }
    
    private function sourceModifiesParameter(string $source, string $param, int $index): bool
    {
        $patterns = [
            "/\\\$args\[{$index}\]\s*=/",
            "/\\\$args\[{$index}\]\s*\*=|\/=|\+=|-=/",
            "/\\\${$param}\s*=/",
            "/\\\${$param}\s*\*=|\/=|\+=|-=/",
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function detectParameterConflicts(array $modifyingHooks): array
    {
        $conflicts = [];
        $parameterModifiers = [];
        

        foreach ($modifyingHooks as $hookClass => $parameters) {
            foreach ($parameters as $param) {
                if (!isset($parameterModifiers[$param])) {
                    $parameterModifiers[$param] = [];
                }
                $parameterModifiers[$param][] = $hookClass;
            }
        }
        

        foreach ($parameterModifiers as $param => $hooks) {
            if (count($hooks) > 1) {
                $conflicts[$param] = $hooks;
            }
        }
        
        return $conflicts;
    }
    
    private function displaySideEffects(array $hooksWithSideEffects): void
    {
        if (empty($hooksWithSideEffects)) {
            $this->line("  ✅ No hooks with detected side effects");
            return;
        }
        
        foreach ($hooksWithSideEffects as $lifecycle => $hooks) {
            $this->line("  📝 {$lifecycle}:");
            foreach ($hooks as $hookClass => $parameters) {
                $paramList = implode(', ', $parameters);
                $this->line("    - {$hookClass} modifies: [{$paramList}]");
            }
        }
    }
    
    private function displayPotentialIssues(array $potentialIssues): void
    {
        if (empty($potentialIssues)) {
            $this->line("  ✅ No potential parameter conflicts detected");
            return;
        }
        
        $this->line('');
        $this->warn("Potential Issues:");
        
        foreach ($potentialIssues as $lifecycle => $conflicts) {
            foreach ($conflicts as $param => $hooks) {
                $hookList = array_map(function($hook) {
                    return class_basename($hook);
                }, $hooks);
                
                $this->line("  ⚠️  Multiple hooks modify '{$param}' in {$lifecycle}: " . implode(', ', $hookList));
                $this->line("     Order matters! Check execution sequence in Kernel configuration.");
            }
        }
    }
}
