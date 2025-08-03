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
     * Create a new template tracker instance.
     */
    public function __construct()
    {
        $this->templates = collect();
    }

    /**
     * Register the view tracker.
     */
    public function register(): void
    {
        View::creator('*', function ($view) {
            $this->trackView($view);
        });
    }

    /**
     * Track a single view.
     */
    protected function trackView($view): void
    {
        $path = $view->getPath();

        if (!$path) {
            return;
        }

        $relativePath = str_replace(base_path() . '/', '', $path);

        $this->templates->push([
            'path' => $relativePath,
            'data' => $this->filterData($view->getData())
        ]);
    }

    /**
     * Filter sensitive data from view data.
     */
    protected function filterData(array $data): array
    {
        $hiddenFields = SaciConfig::getHiddenFields();

        return collect($data)
            ->reject(fn($value, $key) => in_array($key, $hiddenFields))
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