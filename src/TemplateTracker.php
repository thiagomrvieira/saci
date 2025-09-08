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
        $this->maxItems = (int) SaciConfig::get('dump.max_items', 10);
        $this->maxStringLength = (int) SaciConfig::get('dump.max_string_length', 200);
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

        return collect($data)
            ->reject(function ($value, $key) use ($hiddenFields) {
                return in_array($key, $hiddenFields, true) || in_array($key, self::LARAVEL_GLOBALS, true);
            })
            ->map(fn($value) => $this->normalizeValue($value, 0))
            ->toArray();
    }

    /**
     * Normalize any PHP value into a safe, serializable structure with type and preview.
     * Limits depth, string length, and collection size to avoid heavy dumps.
     */
    protected function normalizeValue($value, int $depth = 0): array
    {
        $type = gettype($value);

        // Depth guard
        if ($depth > $this->maxDepth) {
            return [
                'type' => $type,
                'preview' => '…',
                'value' => null,
                'truncated' => true
            ];
        }

        switch ($type) {
            case 'NULL':
                return ['type' => 'null', 'preview' => 'null', 'value' => null, 'truncated' => false];
            case 'boolean':
                return ['type' => 'bool', 'preview' => $value ? 'true' : 'false', 'value' => $value, 'truncated' => false];
            case 'integer':
            case 'double':
                return ['type' => $type === 'double' ? 'float' : 'int', 'preview' => (string)$value, 'value' => $value, 'truncated' => false];
            case 'string':
                $length = mb_strlen($value);
                $isTruncated = $length > $this->maxStringLength;
                $display = $isTruncated ? (mb_substr($value, 0, $this->maxStringLength) . '…') : $value;
                return [
                    'type' => 'string',
                    'preview' => '"' . ($isTruncated ? mb_substr($value, 0, 50) . '…' : (mb_strlen($value) > 50 ? mb_substr($value, 0, 50) . '…' : $value)) . '" (len ' . $length . ')',
                    'value' => $display,
                    'truncated' => $isTruncated
                ];
            case 'array':
                $count = count($value);
                $normalizedChildren = [];
                $i = 0;
                foreach ($value as $k => $v) {
                    if ($i >= $this->maxItems) {
                        break;
                    }
                    $child = $this->normalizeValue($v, $depth + 1);
                    $normalizedChildren[$k] = $child;
                    $i++;
                }

                // Simplify for display: keep only children's values to avoid wrapper noise
                $simplified = [];
                foreach ($normalizedChildren as $k => $child) {
                    $simplified[$k] = is_array($child) && array_key_exists('value', $child) ? $child['value'] : $child;
                }

                return [
                    'type' => 'array',
                    'preview' => 'array(len ' . $count . ')',
                    'value' => $simplified,
                    'truncated' => $count > $this->maxItems
                ];
            case 'object':
                $class = get_class($value);

                // DateTime-like
                if ($value instanceof \DateTimeInterface) {
                    $formatted = $value->format(DATE_ATOM);
                    return ['type' => $class, 'preview' => $formatted, 'value' => $formatted, 'truncated' => false];
                }

                // Laravel Collection
                if ($value instanceof \Illuminate\Support\Collection) {
                    $count = method_exists($value, 'count') ? $value->count() : null;
                    $array = $value->take($this->maxItems)->toArray();
                    $normalized = $this->normalizeValue($array, $depth + 1);
                    return [
                        'type' => $class,
                        'preview' => $class . '(' . ($count !== null ? 'count ' . $count : 'collection') . ')',
                        'value' => $normalized['value'],
                        'truncated' => $count !== null ? $count > $this->maxItems : ($normalized['truncated'] ?? false)
                    ];
                }

                // Eloquent Model (attributes only)
                if (is_subclass_of($value, \Illuminate\Database\Eloquent\Model::class)) {
                    try {
                        $attributes = method_exists($value, 'getAttributes') ? $value->getAttributes() : [];
                    } catch (\Throwable $e) {
                        $attributes = [];
                    }
                    $normalized = $this->normalizeValue($attributes, $depth + 1);
                    $id = method_exists($value, 'getKey') ? $value->getKey() : null;
                    $preview = $class . ($id !== null ? ' (id: ' . $id . ')' : '');
                    return [
                        'type' => $class,
                        'preview' => $preview,
                        'value' => $normalized['value'],
                        'truncated' => $normalized['truncated']
                    ];
                }

                // JsonSerializable
                if ($value instanceof \JsonSerializable) {
                    try {
                        $data = $value->jsonSerialize();
                    } catch (\Throwable $e) {
                        $data = ['__error__' => 'jsonSerialize failed'];
                    }
                    $normalized = $this->normalizeValue($data, $depth + 1);
                    return [
                        'type' => $class,
                        'preview' => $class,
                        'value' => $normalized['value'],
                        'truncated' => $normalized['truncated']
                    ];
                }

                // Stringable
                if (method_exists($value, '__toString')) {
                    try {
                        $string = (string)$value;
                    } catch (\Throwable $e) {
                        $string = $class;
                    }
                    $stringNorm = $this->normalizeValue($string, $depth + 1);
                    return [
                        'type' => $class,
                        'preview' => $class,
                        'value' => $stringNorm['value'],
                        'truncated' => $stringNorm['truncated']
                    ];
                }

                // Fallback: public props
                $props = get_object_vars($value);
                if (!empty($props)) {
                    $normalized = [];
                    $i = 0;
                    foreach ($props as $k => $v) {
                        if ($i >= $this->maxItems) {
                            break;
                        }
                        $normalized[$k] = $this->normalizeValue($v, $depth + 1);
                        $i++;
                    }
                    return [
                        'type' => $class,
                        'preview' => $class . '(object)',
                        'value' => $normalized,
                        'truncated' => count($props) > $this->maxItems
                    ];
                }

                return [
                    'type' => $class,
                    'preview' => $class,
                    'value' => null,
                    'truncated' => false
                ];
            default:
                return ['type' => $type, 'preview' => $type, 'value' => null, 'truncated' => false];
        }
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