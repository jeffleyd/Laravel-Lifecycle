<?php

namespace PhpDiffused\Lifecycle\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Illuminate\Container\Container;
use PhpDiffused\Lifecycle\LifeCycleManager;
use PhpDiffused\Lifecycle\LifeCycleServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected Container $container;
    protected LifeCycleManager $manager;
    protected LifeCycleServiceProvider $provider;
    
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        Container::setInstance($this->container);

        $this->container->bind('config', function () {
            return new class {
                public function get($key, $default = null) {
                    return match ($key) {
                        'lifecycle.debug' => false,
                        'lifecycle.error_handling.log_failures' => true,
                        'lifecycle.error_handling.throw_on_critical' => true,
                        default => $default
                    };
                }
            };
        });

        $this->provider = new LifeCycleServiceProvider($this->container);

        $this->manager = new LifeCycleManager();
        
        // Set up test kernel if it exists
        if (class_exists('PhpDiffused\Lifecycle\Tests\Feature\TestKernel')) {
            $kernel = new \PhpDiffused\Lifecycle\Tests\Feature\TestKernel();
            $this->manager->setHooksKernel($kernel);
        }
        
        $this->container->singleton(LifeCycleManager::class, function () {
            return $this->manager;
        });
    }
    
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }
}