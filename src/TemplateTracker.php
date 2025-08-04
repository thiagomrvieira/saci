<?php

namespace ThiagoVieira\Saci;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use ThiagoVieira\Saci\SaciConfig;

class TemplateTracker
{
    /**
     * Collection of tracked templates.
     */
    protected Collection $templates;

    /**
     * Track view start times.
     */
    protected Collection $viewStartTimes;

    /**
     * Create a new template tracker instance.
     */
    public function __construct()
    {
        $this->templates = collect();
        $this->viewStartTimes = collect();
    }

    /**
     * Register the view tracker.
     */
    public function register(): void
    {
        View::creator('*', function ($view) {
            $this->trackViewStart($view);
        });

        View::composer('*', function ($view) {
            $this->trackViewEnd($view);
        });
    }

    /**
     * Track view start time.
     */
    protected function trackViewStart($view): void
    {
        if (!SaciConfig::isPerformanceTrackingEnabled()) {
            return;
        }

        $path = $view->getPath();

        if (!$path) {
            return;
        }

        $relativePath = str_replace(base_path() . '/', '', $path);

        // Store start time for this view
        $this->viewStartTimes->put($relativePath, microtime(true));
    }

    /**
     * Track view end time and calculate duration.
     */
    protected function trackViewEnd($view): void
    {
        $path = $view->getPath();

        if (!$path) {
            return;
        }

        $relativePath = str_replace(base_path() . '/', '', $path);

        if (SaciConfig::isPerformanceTrackingEnabled()) {
            $endTime = microtime(true);

            // Get start time and calculate duration
            $startTime = $this->viewStartTimes->get($relativePath);
            $duration = $startTime ? ($endTime - $startTime) * 1000 : 0; // Convert to milliseconds

            // Remove start time from tracking
            $this->viewStartTimes->forget($relativePath);
        } else {
            $duration = 0;
        }

        $this->templates->push([
            'path' => $relativePath,
            'data' => $this->filterData($view->getData()),
            'duration' => round($duration, 2)
        ]);
    }

    /**
     * Filter sensitive data from view data.
     */
    protected function filterData(array $data): array
    {
        $hiddenFields = SaciConfig::getHiddenFields();

        // Laravel global variables that should be hidden
        $laravelGlobals = ['__env', 'app', 'errors', '__data', '__path'];

        return collect($data)
            ->reject(fn($value, $key) => in_array($key, $hiddenFields) || in_array($key, $laravelGlobals))
            ->map(fn($value) => gettype($value))
            ->toArray();
    }

    /**
     * Get all tracked templates.
     */
    public function getTemplates(): array
    {
        return $this->templates->toArray();
    }

    /**
     * Get the total number of templates.
     */
    public function getTotal(): int
    {
        return $this->templates->count();
    }

    /**
     * Clear all tracked templates.
     */
    public function clear(): void
    {
        $this->templates = collect();
    }
}