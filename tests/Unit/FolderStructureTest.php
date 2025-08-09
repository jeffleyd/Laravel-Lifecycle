<?php

namespace PhpDiffused\Lifecycle\Tests\Unit;

use PhpDiffused\Lifecycle\Tests\TestCase;
use PhpDiffused\Lifecycle\LifeCycleServiceProvider;

class FolderStructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->container->bind('config', function () {
            return new class {
                public function get($key, $default = null) {
                    return match ($key) {
                        'lifecycle.auto_discovery' => false,
                        'lifecycle.discovery_path' => '/fake/path/Hooks',
                        default => $default
                    };
                }
            };
        });

        $this->provider = new class($this->container) extends LifeCycleServiceProvider {
            public function resolveHooksFor(string $class): \Illuminate\Support\Collection
            {
                return collect();
            }
            
            public function getLifecycleFolderNames(string $lifecycle): array
            {
                return parent::getLifecycleFolderNames($lifecycle);
            }
        };
    }
    
    public function test_can_convert_lifecycle_names_to_folder_names(): void
    {
        $method = new \ReflectionMethod($this->provider, 'getLifecycleFolderNames');
        $method->setAccessible(true);

        $folders = $method->invoke($this->provider, 'paymentBegin');
        $this->assertContains('paymentBegin', $folders);
        $this->assertContains('payment_begin', $folders);
        $this->assertContains('Payment_Begin', $folders);
        $this->assertNotContains('payment.begin', $folders);

        $folders = $method->invoke($this->provider, 'payment_complete');
        $this->assertContains('payment_complete', $folders);
        $this->assertContains('PaymentComplete', $folders);
        $this->assertContains('Payment_Complete', $folders);
        $this->assertNotContains('payment.complete', $folders);

        $folders = $method->invoke($this->provider, 'payment.failed');
        $this->assertContains('PaymentFailed', $folders);
        $this->assertContains('Payment_Failed', $folders);
        $this->assertContains('payment_failed', $folders);
        $this->assertNotContains('payment.failed', $folders);

        foreach ($folders as $folder) {
            $this->assertStringNotContainsString('.', $folder, "Folder name should not contain dots: {$folder}");
        }
    }
    
    public function test_discovers_hooks_returns_empty_collection(): void
    {
        $hooks = $this->provider->resolveHooksFor(TestServiceWithOldStructure::class);
        
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $hooks);
        $this->assertCount(0, $hooks);
    }
    
    public function test_folder_name_conversion_edge_cases(): void
    {
        $method = new \ReflectionMethod($this->provider, 'getLifecycleFolderNames');
        $method->setAccessible(true);

        $folders = $method->invoke($this->provider, 'start');
        $this->assertContains('start', $folders);

        $folders = $method->invoke($this->provider, 'PaymentStart');
        $this->assertContains('PaymentStart', $folders);
        $this->assertContains('payment_start', $folders);
        $this->assertContains('Payment_Start', $folders);

        foreach ($folders as $folder) {
            $this->assertStringNotContainsString('.', $folder);
        }

        $folders = $method->invoke($this->provider, 'user.profile.update');
        $this->assertContains('UserProfileUpdate', $folders);
        $this->assertContains('user_profile_update', $folders);
        $this->assertContains('User_Profile_Update', $folders);
        $this->assertNotContains('user.profile.update', $folders);

        foreach ($folders as $folder) {
            $this->assertStringNotContainsString('.', $folder, "Folder name should not contain dots: {$folder}");
        }
    }
}

class TestServiceWithOldStructure implements \PhpDiffused\Lifecycle\Contracts\LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'process' => ['data'],
            'complete' => ['result'],
        ];
    }
}

class TestServiceWithNewStructure implements \PhpDiffused\Lifecycle\Contracts\LifeCycle
{
    public static function lifeCycle(): array
    {
        return [
            'paymentBegin' => ['amount', 'currency'],
            'payment_complete' => ['transactionId'],
            'payment.failed' => ['error'],
        ];
    }
}