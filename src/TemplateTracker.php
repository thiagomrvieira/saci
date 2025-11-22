<?php

namespace ThiagoVieira\Saci;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as IlluminateView;
use ThiagoVieira\Saci\SaciConfig;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\DumpStorage;

class TemplateTracker
{
    /**
     * Laravel-provided globals that should not be exposed in the debug UI.
     */
    private const LARAVEL_GLOBALS = ['__env', 'app', 'errors', '__data', '__path'];

    /**
     * Collection of tracked templates.
     */
    protected Collection $templates;

    /**
     * Track view start times.
     */
    protected Collection $viewStartTimes;

    /**
     * Normalization settings.
     */
    // Legacy normalization settings (no longer used, retained for BC earlier) removed
    private string $requestId;

    /**
     * Create a new template tracker instance.
     */
    public function __construct(
        protected DumpManager $dumpManager,
        protected DumpStorage $storage,
    )
    {
        $this->templates = collect();
        $this->viewStartTimes = collect();

        $this->requestId = $this->storage->generateRequestId();
    }

    /**
     * Register the view tracker.
     */
    public function register(): void
    {
        View::creator('*', function (IlluminateView $view): void {
            $this->trackViewStart($view);
        });

        View::composer('*', function (IlluminateView $view): void {
            $this->trackViewEnd($view);
        });
    }

    /**
     * Track view start time.
     */
    protected function trackViewStart(IlluminateView $view): void
    {
        if (!SaciConfig::isPerformanceTrackingEnabled()) {
            return;
        }

        $path = $view->getPath();

        if (!$path) {
            return;
        }

        $relativePath = $this->toRelativePath($path);

        // Skip tracking Saci's own views to prevent recursion
        if ($this->isSaciView($relativePath)) {
            return;
        }

        // Store start time for this view
        $this->viewStartTimes->put($relativePath, microtime(true));
    }

    /**
     * Track view end time and calculate duration.
     */
    protected function trackViewEnd(IlluminateView $view): void
    {
        $path = $view->getPath();

        if (!$path) {
            return;
        }

        $relativePath = $this->toRelativePath($path);

        // Skip tracking Saci's own views to prevent recursion
        if ($this->isSaciView($relativePath)) {
            return;
        }

        if (SaciConfig::isPerformanceTrackingEnabled()) {
            $endTime = microtime(true);

            // Get start time and calculate duration
            $startTime = $this->viewStartTimes->get($relativePath);
            $duration = $startTime ? ($endTime - $startTime) * 1000 : 0; // Convert to milliseconds

            // Remove start time from tracking
            $this->viewStartTimes->forget($relativePath);
        } else {
            $duration = null;
        }

        $entry = [
            'path' => $relativePath,
            'data' => $this->filterData($view->getData()),
        ];

        if ($duration !== null) {
            $entry['duration'] = round($duration, 2);
        }

        $this->templates->push($entry);
    }

    /**
     * Reset internal state for a new request lifecycle.
     */
    public function resetForRequest(): void
    {
        $this->templates = collect();
        $this->viewStartTimes = collect();
        $this->requestId = $this->storage->generateRequestId();
    }

    /**
     * Convert an absolute path into a project-relative path.
     */
    protected function toRelativePath(string $absolutePath): string
    {
        return str_replace(base_path() . '/', '', $absolutePath);
    }

    /**
     * Check if the view is from Saci package to prevent recursion.
     */
    protected function isSaciView(string $relativePath): bool
    {
        return str_contains($relativePath, 'vendor/thiago-vieira/saci/src/Resources/views')
            || str_contains($relativePath, '/saci/src/Resources/views');
    }

    /**
     * Filter sensitive data from view data.
     */
    protected function filterData(array $data): array
    {
        $hiddenFields = SaciConfig::getHiddenFields();
        $ignoredKeys = (array) SaciConfig::get('ignore_view_keys', []);
        $maskKeys = (array) SaciConfig::get('mask_keys', []);

        return collect($data)
            ->reject(function ($value, $key) use ($hiddenFields, $ignoredKeys) {
                if (in_array($key, $ignoredKeys, true)) return true;
                return in_array($key, $hiddenFields, true) || in_array($key, self::LARAVEL_GLOBALS, true);
            })
            ->map(function ($value, $key) use ($maskKeys) {
                try {
                    $normalized = $this->normalizeValue($value, 0);
                    if ($this->shouldMaskKey((string) $key, $maskKeys)) {
                        return [
                            'type' => $normalized['type'] ?? get_debug_type($value),
                            'preview' => '[masked]',
                            'dump_id' => null,
                        ];
                    }
                    return $normalized;
                } catch (\Throwable $e) {
                    return [
                        'type' => is_object($value) ? get_class($value) : get_debug_type($value),
                        'preview' => 'unserializable',
                        'dump_id' => null,
                    ];
                }
            })
            ->toArray();
    }

    protected function shouldMaskKey(string $key, array $maskKeys): bool
    {
        if (in_array($key, $maskKeys, true)) return true;
        foreach ($maskKeys as $pattern) {
            if (@preg_match($pattern, '') !== false && preg_match($pattern, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a preview and a dumpId for lazy loading, using VarDumper without side effects.
     */
    protected function normalizeValue(mixed $value, int $depth = 0): array
    {
        // Build preview via DumpManager with strict limits
        $preview = $this->dumpManager->buildPreview($value);
        // Store full dump HTML for lazy render
        $dumpId = $this->dumpManager->storeDump($this->requestId, $value);
        $type = is_object($value) ? get_class($value) : get_debug_type($value);

        return [
            'type' => $type,
            'preview' => $preview,
            'dump_id' => $dumpId,
        ];
    }


    /**
     * Get all tracked templates.
     */
    public function getTemplates(): array
    {
        return $this->templates->toArray();
    }

    public function getRequestId(): string
    {
        return $this->requestId;
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