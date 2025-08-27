<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\Console\AnalyzeLifecycleCommand;
use PhpDiffused\Lifecycle\Attributes\LifeCyclePoint;
use PhpDiffused\Lifecycle\Attributes\Hook;
use PhpDiffused\Lifecycle\Attributes\Severity;
use PhpDiffused\Lifecycle\Traits\HasLifecycle;
use PhpDiffused\Lifecycle\Traits\Hookable;
use ReflectionClass;

class AnalyzeLifecycleCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
    
    public function test_command_class_exists_and_has_correct_methods(): void
    {
        $this->assertTrue(class_exists(AnalyzeLifecycleCommand::class));
        
        $reflection = new ReflectionClass(AnalyzeLifecycleCommand::class);
        $this->assertTrue($reflection->hasMethod('handle'));
        $this->assertTrue($reflection->hasProperty('signature'));
        $this->assertTrue($reflection->hasProperty('description'));
    }
    
    public function test_can_get_lifecycle_points_from_test_class(): void
    {
        $reflection = new ReflectionClass(AnalyzeTestPaymentService::class);
        $attributes = $reflection->getAttributes(LifeCyclePoint::class);
        
        $this->assertCount(3, $attributes);
        
        $points = [];
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $points[] = $instance->name;
        }
        
        $this->assertContains('before_payment', $points);
        $this->assertContains('after_payment', $points);
        $this->assertContains('payment_failed', $points);
    }
    
    public function test_hooks_have_correct_attributes(): void
    {
        $criticalHook = new AnalyzeTestCriticalHook();
        $optionalHook = new AnalyzeTestOptionalHook();
        
        $this->assertEquals('critical', $criticalHook->getSeverity());
        $this->assertEquals('optional', $optionalHook->getSeverity());
        $this->assertEquals('before_payment', $criticalHook->getLifeCycle());
        $this->assertEquals('after_payment', $optionalHook->getLifeCycle());
    }
    
    public function test_side_effect_detection_methods_exist(): void
    {
        $reflection = new ReflectionClass(AnalyzeLifecycleCommand::class);
        
        $this->assertTrue($reflection->hasMethod('analyzeSideEffects'));
        $this->assertTrue($reflection->hasMethod('detectPotentialModifications'));
        $this->assertTrue($reflection->hasMethod('sourceModifiesParameter'));
    }
}

#[LifeCyclePoint('before_payment', ['amount', 'user_id'])]
#[LifeCyclePoint('after_payment', ['amount', 'user_id', 'payment_id'])]
#[LifeCyclePoint('payment_failed', ['amount', 'user_id', 'error'])]
class AnalyzeTestPaymentService
{
    use HasLifecycle;
}

#[Hook(scope: 'payment', point: 'before_payment', severity: Severity::Critical)]
class AnalyzeTestCriticalHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args[0] = $args[0] * 1.1;
    }
}

#[Hook(scope: 'payment', point: 'after_payment', severity: Severity::Optional)]
class AnalyzeTestOptionalHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        logger()->info('Payment completed', ['amount' => $args[0]]);
    }
}

#[Hook(scope: 'payment', point: 'before_payment', severity: Severity::Optional)]
class AnalyzeTestModifyingHook
{
    use Hookable;
    
    public function handle(array &$args): void
    {
        $args[0] = $args[0] * 0.9;
        $args[1] = $args[1] + 1000;
    }
}
