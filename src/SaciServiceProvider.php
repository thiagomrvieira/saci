<?php

namespace ThiagoVieira\Saci;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use ThiagoVieira\Saci\SaciConfig;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\DumpStorage;
use ThiagoVieira\Saci\Support\LogCollector as SupportLogCollector;
use ThiagoVieira\Saci\Support\LogProcessor;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\Support\FilePathResolver;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\Http\Controllers\DumpController;
use ThiagoVieira\Saci\Http\Controllers\AssetsController;
use ThiagoVieira\Saci\Collectors\ViewCollector;
use ThiagoVieira\Saci\Collectors\RequestCollector;
use ThiagoVieira\Saci\Collectors\RouteCollector;
use ThiagoVieira\Saci\Collectors\AuthCollector;
use ThiagoVieira\Saci\Collectors\LogCollector;

class SaciServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/Resources/config/saci.php',
            'saci'
        );

        $this->registerServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'saci');

        $this->registerMiddleware();
        $this->registerRoutes();
        $this->publishConfig();
        $this->publishAssets();
    }

    /**
     * Register the middleware.
     */
    protected function registerMiddleware(): void
    {
        if (!SaciConfig::isAutoRegistrationEnabled()) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);
        $kernel->pushMiddleware(SaciMiddleware::class);
    }

    /**
     * Register the package services.
     */
    protected function registerServices(): void
    {
        // Core storage and dump management
        $this->app->singleton(DumpStorage::class, function($app) {
            $caps = config('saci.caps', []);
            $perRequestBytes = (int) ($caps['per_request_bytes'] ?? 1048576);
            $ttl = (int) ($caps['ttl_seconds'] ?? 60);
            return new DumpStorage('local', $perRequestBytes, $ttl);
        });

        $this->app->singleton(DumpManager::class, function($app) {
            $limits = config('saci.dump', []);
            return new DumpManager($app->make(DumpStorage::class), $limits);
        });

        // Log collection and processing (Support layer)
        $this->app->singleton(FilePathResolver::class);
        $this->app->singleton(SupportLogCollector::class);
        $this->app->singleton(LogProcessor::class);
        $this->app->singleton(LateLogsPersistence::class);

        // Core components
        $this->app->singleton(TemplateTracker::class);
        $this->app->singleton(DebugBarInjector::class);
        $this->app->singleton(RequestValidator::class);

        // Collector Registry and Collectors
        $this->registerCollectors();

        // Backward compatibility: RequestResources → Adapter
        $this->app->singleton(RequestResources::class, function($app) {
            return $app->make(RequestResourcesAdapter::class);
        });
    }

    /**
     * Register collectors in the registry.
     */
    protected function registerCollectors(): void
    {
        $this->app->singleton(CollectorRegistry::class, function($app) {
            $registry = new CollectorRegistry();

            // Register core collectors
            $registry->register($app->make(ViewCollector::class));
            $registry->register($app->make(RequestCollector::class));
            $registry->register($app->make(RouteCollector::class));
            $registry->register($app->make(AuthCollector::class));
            $registry->register($app->make(LogCollector::class));

            return $registry;
        });

        // Register individual collectors as singletons
        $this->app->singleton(ViewCollector::class);
        $this->app->singleton(RequestCollector::class);
        $this->app->singleton(RouteCollector::class);
        $this->app->singleton(AuthCollector::class);
        $this->app->singleton(LogCollector::class);
    }

    protected function registerRoutes(): void
    {
        $router = $this->app['router'];
        $router->get('/__saci/dump/{requestId}/{dumpId}', [DumpController::class, 'show'])->middleware('web');
        $router->get('/__saci/late-logs/{requestId}', [DumpController::class, 'lateLogs'])->middleware('web');

        // Always register asset routes; controller guards serving based on config and client
        $router->group(['prefix' => '/__saci/assets', 'middleware' => ['web']], function($router) {
            $router->get('/saci.css', [AssetsController::class, 'css']);
            $router->get('/saci.js', [AssetsController::class, 'js']);
            // Extensionless variants to avoid webserver static-file interception
            $router->get('/css', [AssetsController::class, 'css']);
            $router->get('/js', [AssetsController::class, 'js']);
        });
    }



    /**
     * Publish configuration files.
     */
    protected function publishConfig(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/Resources/config/saci.php' => config_path('saci.php'),
        ], 'saci-config');
    }

    /**
     * Publish public assets (CSS/JS/images).
     */
    protected function publishAssets(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/Resources/assets' => public_path('vendor/saci'),
        ], 'saci-assets');
    }
}