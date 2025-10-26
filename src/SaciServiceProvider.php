<?php

namespace ThiagoVieira\Saci;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Http\Kernel;
use ThiagoVieira\Saci\SaciConfig;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\DumpStorage;
use ThiagoVieira\Saci\Http\Controllers\DumpController;
use ThiagoVieira\Saci\Http\Controllers\AssetsController;

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
        $this->app->singleton(TemplateTracker::class);
        $this->app->singleton(DebugBarInjector::class);
        $this->app->singleton(RequestValidator::class);
        $this->app->singleton(RequestResources::class);
    }

    protected function registerRoutes(): void
    {
        $router = $this->app['router'];
        $router->get('/__saci/dump/{requestId}/{dumpId}', [DumpController::class, 'show'])->middleware('web');

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