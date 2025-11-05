# Collectors Architecture

## Overview

Collectors are the heart of Saci's data collection system. Each collector is responsible for gathering a specific type of data during the request lifecycle.

## Creating a New Collector

### 1. Create the Collector Class

Create a new class in `src/Collectors/` that extends `BaseCollector`:

```php
<?php

namespace ThiagoVieira\Saci\Collectors;

class MyCustomCollector extends BaseCollector
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'mycustom'; // Unique identifier
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return 'My Custom Data'; // Display label
    }

    /**
     * {@inheritdoc}
     */
    protected function doStart(): void
    {
        // Initialize collection (e.g., register listeners)
    }

    /**
     * {@inheritdoc}
     */
    protected function doCollect(): void
    {
        // Collect and store data in $this->data
        $this->data = [
            'items' => $this->collectItems(),
            'count' => $this->countItems(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doReset(): void
    {
        // Clean up resources if needed
    }
}
```

### 2. Register the Collector

Add your collector to `SaciServiceProvider::registerCollectors()`:

```php
protected function registerCollectors(): void
{
    $this->app->singleton(CollectorRegistry::class, function($app) {
        $registry = new CollectorRegistry();

        // Existing collectors...
        $registry->register($app->make(ViewCollector::class));

        // Your new collector
        $registry->register($app->make(MyCustomCollector::class));

        return $registry;
    });

    // Register as singleton
    $this->app->singleton(MyCustomCollector::class);
}
```

### 3. Add Configuration (Optional)

Add config option in `src/Resources/config/saci.php`:

```php
'collectors' => [
    // Existing collectors...
    'mycustom' => env('SACI_COLLECTOR_MYCUSTOM', true),
],
```

### 4. Create View Template (Optional)

Create a blade view in `src/Resources/views/partials/`:

```php
<!-- resources/views/partials/mycustom-tab.blade.php -->
<div class="saci-card">
    <h3>My Custom Data</h3>
    @foreach($data['items'] as $item)
        <div>{{ $item }}</div>
    @endforeach
</div>
```

## Collector Lifecycle

1. **Registration**: Collector is registered in `CollectorRegistry` during service provider boot
2. **Start**: `start()` is called at the beginning of the request (before controller)
3. **Collect**: `collect()` is called after the response is generated
4. **Data Access**: `getData()` returns collected data for rendering
5. **Reset**: `reset()` prepares for next request

## Available Collectors

### ViewCollector
Tracks Blade views and templates loaded during the request.

**Data Structure:**
```php
[
    'templates' => [...],
    'total' => 5,
    'request_id' => 'uuid',
]
```

### RequestCollector
Collects HTTP request and response metadata.

**Data Structure:**
```php
[
    'request' => [...],
    'response' => [...],
]
```

### RouteCollector
Collects route and controller information.

**Data Structure:**
```php
[
    'name' => 'home',
    'uri' => '/',
    'methods' => ['GET'],
    'controller' => 'HomeController',
    // ...
]
```

### AuthCollector
Collects authentication data.

**Data Structure:**
```php
[
    'guard' => 'web',
    'authenticated' => true,
    'id' => 1,
    'email' => 'user@example.com',
]
```

### LogCollector
Collects application logs during request.

**Data Structure:**
```php
[
    'logs' => [
        ['level' => 'info', 'message' => '...', ...],
    ],
]
```

## Best Practices

1. **Single Responsibility**: Each collector should focus on one type of data
2. **Minimal Overhead**: Keep `doStart()` lightweight; heavy work goes in `doCollect()`
3. **Graceful Failure**: Wrap risky operations in try-catch
4. **Type Safety**: Use type hints and return types
5. **Documentation**: Add PHPDoc comments explaining data structure
6. **Testing**: Write tests for your collector

## Examples

### Simple Collector (No Dependencies)

```php
class SimpleCollector extends BaseCollector
{
    protected array $items = [];

    public function getName(): string { return 'simple'; }
    public function getLabel(): string { return 'Simple'; }

    protected function doStart(): void
    {
        $this->items = [];
    }

    protected function doCollect(): void
    {
        $this->data = ['items' => $this->items];
    }
}
```

### Collector with Dependencies

```php
class DatabaseCollector extends BaseCollector
{
    public function __construct(
        protected ConnectionResolverInterface $db
    ) {}

    protected function doStart(): void
    {
        $this->db->listen(function($query) {
            $this->queries[] = $query;
        });
    }

    protected function doCollect(): void
    {
        $this->data = [
            'queries' => $this->queries,
            'total_time' => array_sum(array_column($this->queries, 'time')),
        ];
    }
}
```

### Collector with Request Access

```php
class CustomRequestCollector extends BaseCollector
{
    protected ?Request $request = null;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    protected function doCollect(): void
    {
        if ($this->request) {
            $this->data = [
                'custom_header' => $this->request->header('X-Custom'),
            ];
        }
    }
}
```

## Extensibility

The collector architecture is designed to be extensible:

- Add new collectors without modifying core code
- Toggle collectors on/off via configuration
- Inject dependencies via constructor
- Access shared services (DumpManager, etc.)
- Hook into request lifecycle events

For more architectural details, see `ARCHITECTURE.md`.


