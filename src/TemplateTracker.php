<?php

namespace ThiagoVieira\Saci;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as IlluminateView;
use ThiagoVieira\Saci\SaciConfig;

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
    private int $maxDepth;
    private int $maxItems;
    private int $maxStringLength;

    /**
     * Create a new template tracker instance.
     */
    public function __construct()
    {
        $this->templates = collect();
        $this->viewStartTimes = collect();

        // Allow configuration overrides with sensible defaults
        $this->maxDepth = (int) SaciConfig::get('dump.max_depth', 5);
        $this->maxItems = (int) SaciConfig::get('dump.max_items', 50);
        $this->maxStringLength = (int) SaciConfig::get('dump.max_string_length', 2000);
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
     * Convert an absolute path into a project-relative path.
     */
    protected function toRelativePath(string $absolutePath): string
    {
        return str_replace(base_path() . '/', '', $absolutePath);
    }

    /**
     * Filter sensitive data from view data.
     */
    protected function filterData(array $data): array
    {
        $hiddenFields = SaciConfig::getHiddenFields();
        $ignoredKeys = (array) SaciConfig::get('ignore_view_keys', []);

        return collect($data)
            ->reject(function ($value, $key) use ($hiddenFields, $ignoredKeys) {
                if (in_array($key, $ignoredKeys, true)) return true;
                return in_array($key, $hiddenFields, true) || in_array($key, self::LARAVEL_GLOBALS, true);
            })
            ->map(function ($value) {
                try {
                    return $this->normalizeValue($value, 0);
                } catch (\Throwable $e) {
                    return [
                        'type' => is_object($value) ? get_class($value) : get_debug_type($value),
                        'preview' => 'unserializable',
                        'value' => null,
                        'truncated' => true,
                    ];
                }
            })
            ->toArray();
    }

    /**
     * Normalize any PHP value into a safe, serializable structure with type and preview.
     */
    protected function normalizeValue(mixed $value, int $depth = 0): array
    {
        if ($depth > $this->maxDepth) {
            return $this->truncatedResponse(gettype($value));
        }

        return match (gettype($value)) {
            'NULL'      => $this->simpleResponse('null', 'null', null),
            'boolean'   => $this->simpleResponse('bool', $value ? 'true' : 'false', $value),
            'integer'   => $this->simpleResponse('int', (string) $value, $value),
            'double'    => $this->simpleResponse('float', (string) $value, $value),
            'string'    => $this->normalizeString($value),
            'array'     => $this->normalizeArray($value, $depth),
            'object'    => $this->normalizeObject($value, $depth),
            default     => $this->simpleResponse(gettype($value), gettype($value)),
        };
    }

    private function simpleResponse(string $type, string $preview, mixed $value = null, bool $truncated = false): array
    {
        return compact('type', 'preview', 'value', 'truncated');
    }

    private function truncatedResponse(string $type): array
    {
        return $this->simpleResponse($type, '…', null, true);
    }

    private function normalizeString(string $value): array
    {
        $length = mb_strlen($value);
        $isTruncated = $length > $this->maxStringLength;
        $display = $isTruncated ? mb_substr($value, 0, $this->maxStringLength) . '…' : $value;

        $preview = sprintf('"%s" (len %d)',
            mb_substr($value, 0, 50) . ($length > 50 ? '…' : ''),
            $length
        );

        return $this->simpleResponse('string', $preview, $display, $isTruncated);
    }

    private function normalizeArray(array $value, int $depth): array
    {
        $count = count($value);
        $normalized = [];

        foreach (array_slice($value, 0, $this->maxItems, true) as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item, $depth + 1);
        }

        $simplified = array_map(fn($child) => $child['value'] ?? $child, $normalized);

        return [
            'type' => 'array',
            'preview' => "array(len {$count})",
            'value' => $simplified,
            'truncated' => $count > $this->maxItems,
        ];
    }

    private function normalizeObject(object $value, int $depth): array
    {
        $class = $value::class;

        if ($value instanceof \Closure) {
            return $this->simpleResponse('Closure', 'closure', null, true);
        }

        if ($this->isFrameworkInternal($class)) {
            return $this->simpleResponse($class, $class, null, true);
        }

        if ($value instanceof \DateTimeInterface) {
            $formatted = $value->format(DATE_ATOM);
            return $this->simpleResponse($class, $formatted, $formatted);
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $this->normalizeCollection($value, $class, $depth);
        }

        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            return $this->normalizeModel($value, $class, $depth);
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalizeJsonSerializable($value, $class, $depth);
        }

        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $this->normalizeArrayable($value, $class, $depth);
        }

        if (method_exists($value, '__toString')) {
            return $this->normalizeStringable($value, $class, $depth);
        }

        return $this->normalizeGenericObject($value, $class, $depth);
    }

    private function isFrameworkInternal(string $class): bool
    {
        return str_starts_with($class, 'Illuminate\\')
            || str_starts_with($class, 'Symfony\\')
            || str_starts_with($class, 'Psr\\')
            || str_starts_with($class, 'GuzzleHttp\\');
    }

    private function normalizeCollection($collection, string $class, int $depth): array
    {
        $count = $collection->count();
        $array = $collection->take($this->maxItems)->toArray();
        $normalized = $this->normalizeValue($array, $depth + 1);

        return [
            'type' => $class,
            'preview' => "$class(count {$count})",
            'value' => $normalized['value'],
            'truncated' => $count > $this->maxItems,
        ];
    }

    private function normalizeModel($model, string $class, int $depth): array
    {
        $attributes = rescue(fn() => $model->getAttributes(), [], false);
        $normalized = $this->normalizeValue($attributes, $depth + 1);
        $id = $model->getKey();

        return [
            'type' => $class,
            'preview' => $class . ($id ? " (id: {$id})" : ''),
            'value' => $normalized['value'],
            'truncated' => $normalized['truncated'],
        ];
    }

    private function normalizeJsonSerializable($value, string $class, int $depth): array
    {
        $data = rescue(fn() => $value->jsonSerialize(), ['__error__' => 'jsonSerialize failed'], false);
        $normalized = $this->normalizeValue($data, $depth + 1);

        return [
            'type' => $class,
            'preview' => $class,
            'value' => $normalized['value'],
            'truncated' => $normalized['truncated'],
        ];
    }

    private function normalizeArrayable($value, string $class, int $depth): array
    {
        $array = rescue(fn() => $value->toArray(), [], false);
        return $this->normalizeValue($array, $depth + 1);
    }

    private function normalizeStringable($value, string $class, int $depth): array
    {
        $string = rescue(fn() => (string)$value, $class, false);
        $normalized = $this->normalizeValue($string, $depth + 1);

        return [
            'type' => $class,
            'preview' => $class,
            'value' => $normalized['value'],
            'truncated' => $normalized['truncated'],
        ];
    }

    private function normalizeGenericObject($object, string $class, int $depth): array
    {
        if (!str_starts_with($class, 'App\\')) {
            return $this->simpleResponse($class, $class, null, true);
        }

        $props = rescue(fn() => get_object_vars($object), [], false);
        $limited = array_slice($props, 0, $this->maxItems, true);

        $normalized = array_map(fn($v) => $this->normalizeValue($v, $depth + 1), $limited);

        return [
            'type' => $class,
            'preview' => "{$class}(object)",
            'value' => $normalized,
            'truncated' => count($props) > $this->maxItems,
        ];
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