<?php

declare(strict_types=1);

namespace ThiagoVieira\Saci\Tests;

use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;
use ThiagoVieira\Saci\SaciServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpConfig();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SaciServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Database configuration
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Saci default configuration (enabled for testing)
        Config::set('saci.enabled', true);
        Config::set('saci.allow_ajax', false);
        Config::set('saci.allow_ips', []);

        // Enable all collectors by default
        Config::set('saci.collectors.views', true);
        Config::set('saci.collectors.request', true);
        Config::set('saci.collectors.route', true);
        Config::set('saci.collectors.auth', true);
        Config::set('saci.collectors.logs', true);
        Config::set('saci.collectors.database', true);
    }

    /**
     * Setup test-specific configuration.
     */
    protected function setUpConfig(): void
    {
        // Override config as needed in specific tests
    }

    /**
     * Disable Saci for a specific test.
     */
    protected function disableSaci(): void
    {
        Config::set('saci.enabled', false);
    }

    /**
     * Enable specific collector.
     */
    protected function enableCollector(string $collector): void
    {
        Config::set("saci.collectors.{$collector}", true);
    }

    /**
     * Disable specific collector.
     */
    protected function disableCollector(string $collector): void
    {
        Config::set("saci.collectors.{$collector}", false);
    }

    /**
     * Disable all collectors.
     */
    protected function disableAllCollectors(): void
    {
        $collectors = ['views', 'request', 'route', 'auth', 'logs', 'database'];

        foreach ($collectors as $collector) {
            $this->disableCollector($collector);
        }
    }
}



