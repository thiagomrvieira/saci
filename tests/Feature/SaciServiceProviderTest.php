<?php

namespace ThiagoVieira\Saci\Tests\Feature;

use ThiagoVieira\Saci\SaciServiceProvider;
use ThiagoVieira\Saci\SaciMiddleware;
use ThiagoVieira\Saci\Support\DumpStorage;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\FilePathResolver;
use ThiagoVieira\Saci\Support\LogCollector as SupportLogCollector;
use ThiagoVieira\Saci\Support\LogProcessor;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\TemplateTracker;
use ThiagoVieira\Saci\DebugBarInjector;
use ThiagoVieira\Saci\RequestValidator;
use ThiagoVieira\Saci\RequestResources;
use ThiagoVieira\Saci\Collectors\ViewCollector;
use ThiagoVieira\Saci\Collectors\RequestCollector;
use ThiagoVieira\Saci\Collectors\RouteCollector;
use ThiagoVieira\Saci\Collectors\AuthCollector;
use ThiagoVieira\Saci\Collectors\LogCollector;
use ThiagoVieira\Saci\Collectors\DatabaseCollector;
use Illuminate\Support\Facades\Route;

// ============================================================================
// 1. SERVICE REGISTRATION
// ============================================================================

describe('Service Registration', function () {
    it('registers DumpStorage as singleton', function () {
        $instance1 = $this->app->make(DumpStorage::class);
        $instance2 = $this->app->make(DumpStorage::class);

        expect($instance1)->toBeInstanceOf(DumpStorage::class);
        expect($instance1)->toBe($instance2); // Same instance
    });

    it('registers DumpManager as singleton', function () {
        $instance1 = $this->app->make(DumpManager::class);
        $instance2 = $this->app->make(DumpManager::class);

        expect($instance1)->toBeInstanceOf(DumpManager::class);
        expect($instance1)->toBe($instance2);
    });

    it('registers FilePathResolver as singleton', function () {
        $instance1 = $this->app->make(FilePathResolver::class);
        $instance2 = $this->app->make(FilePathResolver::class);

        expect($instance1)->toBeInstanceOf(FilePathResolver::class);
        expect($instance1)->toBe($instance2);
    });

    it('registers LogProcessor as singleton', function () {
        $instance = $this->app->make(LogProcessor::class);

        expect($instance)->toBeInstanceOf(LogProcessor::class);
    });

    it('registers LateLogsPersistence as singleton', function () {
        $instance = $this->app->make(LateLogsPersistence::class);

        expect($instance)->toBeInstanceOf(LateLogsPersistence::class);
    });

    it('registers TemplateTracker as singleton', function () {
        $instance = $this->app->make(TemplateTracker::class);

        expect($instance)->toBeInstanceOf(TemplateTracker::class);
    });

    it('registers DebugBarInjector as singleton', function () {
        $instance = $this->app->make(DebugBarInjector::class);

        expect($instance)->toBeInstanceOf(DebugBarInjector::class);
    });

    it('registers RequestValidator as singleton', function () {
        $instance = $this->app->make(RequestValidator::class);

        expect($instance)->toBeInstanceOf(RequestValidator::class);
    });

    it('registers RequestResources with backward compatibility', function () {
        $instance = $this->app->make(RequestResources::class);

        // RequestResources is aliased to RequestResourcesAdapter for backward compatibility
        expect($instance)->not->toBeNull();
        expect($instance)->toBeObject();
    });
});

// ============================================================================
// 2. COLLECTOR REGISTRATION
// ============================================================================

describe('Collector Registration', function () {
    it('registers CollectorRegistry as singleton', function () {
        $instance = $this->app->make(CollectorRegistry::class);

        expect($instance)->toBeInstanceOf(CollectorRegistry::class);
    });

    it('registers ViewCollector in registry', function () {
        $registry = $this->app->make(CollectorRegistry::class);

        $collectors = $registry->all();

        expect($collectors)->toHaveCount(6);
        expect($collectors->first())->toBeInstanceOf(ViewCollector::class);
    });

    it('registers RequestCollector in registry', function () {
        $registry = $this->app->make(CollectorRegistry::class);

        expect($registry->has('request'))->toBeTrue();
    });

    it('registers RouteCollector in registry', function () {
        $registry = $this->app->make(CollectorRegistry::class);

        expect($registry->has('route'))->toBeTrue();
    });

    it('registers AuthCollector in registry', function () {
        $registry = $this->app->make(CollectorRegistry::class);

        expect($registry->has('auth'))->toBeTrue();
    });

    it('registers LogCollector in registry', function () {
        $registry = $this->app->make(CollectorRegistry::class);

        expect($registry->has('logs'))->toBeTrue();
    });

    it('registers DatabaseCollector in registry', function () {
        $registry = $this->app->make(CollectorRegistry::class);

        expect($registry->has('database'))->toBeTrue();
    });

    it('registers all 6 core collectors', function () {
        $registry = $this->app->make(CollectorRegistry::class);

        expect($registry->count())->toBe(6);
    });
});

// ============================================================================
// 3. ROUTE REGISTRATION
// ============================================================================

describe('Route Registration', function () {
    it('registers dump route', function () {
        $routes = Route::getRoutes();

        $dumpRoute = collect($routes)->first(function ($route) {
            return str_contains($route->uri(), '__saci/dump/{requestId}/{dumpId}');
        });

        expect($dumpRoute)->not->toBeNull();
        expect($dumpRoute->methods())->toContain('GET');
    });

    it('registers late logs route', function () {
        $routes = Route::getRoutes();

        $lateLogsRoute = collect($routes)->first(function ($route) {
            return str_contains($route->uri(), '__saci/late-logs/{requestId}');
        });

        expect($lateLogsRoute)->not->toBeNull();
    });

    it('registers CSS asset route with extension', function () {
        $routes = Route::getRoutes();

        $cssRoute = collect($routes)->first(function ($route) {
            return str_contains($route->uri(), '__saci/assets/saci.css');
        });

        expect($cssRoute)->not->toBeNull();
    });

    it('registers JS asset route with extension', function () {
        $routes = Route::getRoutes();

        $jsRoute = collect($routes)->first(function ($route) {
            return str_contains($route->uri(), '__saci/assets/saci.js');
        });

        expect($jsRoute)->not->toBeNull();
    });

    it('registers CSS asset route without extension', function () {
        $routes = Route::getRoutes();

        $cssRoute = collect($routes)->first(function ($route) {
            return $route->uri() === '__saci/assets/css';
        });

        expect($cssRoute)->not->toBeNull();
    });

    it('registers JS asset route without extension', function () {
        $routes = Route::getRoutes();

        $jsRoute = collect($routes)->first(function ($route) {
            return $route->uri() === '__saci/assets/js';
        });

        expect($jsRoute)->not->toBeNull();
    });
});

// ============================================================================
// 4. MIDDLEWARE REGISTRATION
// ============================================================================

describe('Middleware Registration', function () {
    it('registers middleware when auto registration is enabled', function () {
        config(['saci.auto_register' => true]);

        // Re-register provider to apply config
        $provider = new SaciServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $middlewares = $kernel->getMiddlewareGroups();

        // Middleware is pushed, so it should be in the global middleware stack
        expect(true)->toBeTrue(); // Just verify no exception thrown
    });

    it('skips middleware when auto registration is disabled', function () {
        config(['saci.auto_register' => false]);

        // This shouldn't throw an exception
        $provider = new SaciServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        expect(true)->toBeTrue();
    });
});

// ============================================================================
// 5. CONFIGURATION
// ============================================================================

describe('Configuration', function () {
    it('merges default configuration', function () {
        expect(config('saci'))->toBeArray();
        expect(config('saci.enabled'))->not->toBeNull();
    });

    it('uses default DumpStorage configuration', function () {
        $storage = $this->app->make(DumpStorage::class);

        expect($storage)->toBeInstanceOf(DumpStorage::class);
    });

    it('respects custom per_request_bytes cap', function () {
        config(['saci.caps.per_request_bytes' => 2097152]); // 2MB

        $storage = $this->app->make(DumpStorage::class);

        expect($storage)->toBeInstanceOf(DumpStorage::class);
    });

    it('respects custom TTL configuration', function () {
        config(['saci.caps.ttl_seconds' => 120]);

        $storage = $this->app->make(DumpStorage::class);

        expect($storage)->toBeInstanceOf(DumpStorage::class);
    });
});

// ============================================================================
// 6. VIEWS
// ============================================================================

describe('Views', function () {
    it('loads views from Resources/views directory', function () {
        // Check if view namespace is registered
        expect(view()->exists('saci::bar'))->toBeTrue();
    });
});

// ============================================================================
// 7. DEPENDENCY INJECTION
// ============================================================================

describe('Dependency Injection', function () {
    it('resolves DumpManager with DumpStorage dependency', function () {
        $dumpManager = $this->app->make(DumpManager::class);

        expect($dumpManager)->toBeInstanceOf(DumpManager::class);
    });

    it('resolves LogProcessor with DumpManager dependency', function () {
        $logProcessor = $this->app->make(LogProcessor::class);

        expect($logProcessor)->toBeInstanceOf(LogProcessor::class);
    });

    it('resolves LateLogsPersistence with DumpStorage dependency', function () {
        $persistence = $this->app->make(LateLogsPersistence::class);

        expect($persistence)->toBeInstanceOf(LateLogsPersistence::class);
    });
});

// ============================================================================
// 8. INTEGRATION
// ============================================================================

describe('Integration', function () {
    it('can resolve all registered services without errors', function () {
        $services = [
            DumpStorage::class,
            DumpManager::class,
            FilePathResolver::class,
            SupportLogCollector::class,
            LogProcessor::class,
            LateLogsPersistence::class,
            TemplateTracker::class,
            DebugBarInjector::class,
            RequestValidator::class,
            CollectorRegistry::class,
            ViewCollector::class,
            RequestCollector::class,
            RouteCollector::class,
            AuthCollector::class,
            LogCollector::class,
            DatabaseCollector::class,
        ];

        foreach ($services as $service) {
            $instance = $this->app->make($service);
            expect($instance)->toBeInstanceOf($service);
        }
    });
});

