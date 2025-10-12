<?php

namespace ThiagoVieira\Saci;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Http\Kernel;
use ThiagoVieira\Saci\SaciConfig;

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
        $this->app->singleton(TemplateTracker::class);
        $this->app->singleton(DebugBarInjector::class);
        $this->app->singleton(RequestValidator::class);
        $this->app->singleton(RequestResources::class);
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