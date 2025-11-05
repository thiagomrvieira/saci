# Architecture Refactoring

## Overview

This document describes the architectural refactoring from a monolithic data collection approach to a modular **Collector Pattern** architecture.

## What Changed

### Before (v2.x)

```
src/
├── SaciMiddleware.php (orchestrator)
├── TemplateTracker.php (view tracking)
├── RequestResources.php (request/route/auth/logs - GOD OBJECT)
└── DebugBarInjector.php (rendering)
```

**Problems:**
- `RequestResources` handled multiple concerns (400+ lines)
- Hard to add new features without modifying core
- No clear separation between data collection types
- Testing was difficult due to tight coupling

### After (v3.x)

```
src/
├── Collectors/
│   ├── Contracts/CollectorInterface.php (contract)
│   ├── BaseCollector.php (base implementation)
│   ├── ViewCollector.php (views)
│   ├── RequestCollector.php (request/response)
│   ├── RouteCollector.php (routing)
│   ├── AuthCollector.php (authentication)
│   └── LogCollector.php (logs)
├── Support/
│   └── CollectorRegistry.php (manages collectors)
├── SaciMiddleware.php (orchestrator)
├── DebugBarInjector.php (rendering)
└── RequestResourcesAdapter.php (backward compatibility)
```

**Benefits:**
- ✅ Single Responsibility: Each collector = one concern
- ✅ Open/Closed: Add collectors without modifying core
- ✅ Testable: Collectors are isolated and mockable
- ✅ Configurable: Enable/disable collectors individually
- ✅ Extensible: Plugin system ready
- ✅ Industry Standard: Follows Symfony/Telescope patterns

## Breaking Changes

### None! 🎉

The refactoring is **100% backward compatible**:
- All existing views continue to work
- `RequestResources` still exists (as an adapter)
- Same data structure passed to views
- Same configuration (with new options added)

## Migration Guide

### For Users

**No action required!** Everything works as before.

### For Developers Extending Saci

If you were using `RequestResources` directly:

#### Before
```php
use ThiagoVieira\Saci\RequestResources;

$resources = app(RequestResources::class);
$data = $resources->getData();
```

#### After (Recommended)
```php
use ThiagoVieira\Saci\Support\CollectorRegistry;

$registry = app(CollectorRegistry::class);
$data = $registry->getAllData();
```

#### After (Backward Compatible)
```php
use ThiagoVieira\Saci\RequestResources;

// Still works! RequestResources is now an adapter
$resources = app(RequestResources::class);
$data = $resources->getData();
```

## New Features

### 1. Individual Collector Control

Enable/disable collectors independently:

```php
// config/saci.php
'collectors' => [
    'views' => true,
    'request' => true,
    'route' => true,
    'auth' => false,  // Disable auth collector
    'logs' => true,
],
```

### 2. Easy Extension

Create custom collectors without modifying core:

```php
use ThiagoVieira\Saci\Collectors\BaseCollector;

class MyCollector extends BaseCollector
{
    public function getName(): string { return 'my_collector'; }
    public function getLabel(): string { return 'My Data'; }

    protected function doCollect(): void
    {
        $this->data = ['custom' => 'data'];
    }
}
```

Register in `SaciServiceProvider`:

```php
$registry->register($app->make(MyCollector::class));
```

### 3. Performance Optimization

Disabled collectors have zero overhead:

```env
SACI_COLLECTOR_AUTH=false  # Auth collector won't run
SACI_COLLECTOR_LOGS=false  # Log collector won't run
```

## Architecture Benefits

### Before: Adding Database Tracking

1. Modify `RequestResources` (already 400+ lines)
2. Add database tracking code
3. Update `getData()` method
4. Hope nothing breaks
5. `RequestResources` now has 500+ lines

### After: Adding Database Tracking

1. Create `DatabaseCollector.php` (50 lines)
2. Register in ServiceProvider (1 line)
3. Create view template (optional)
4. Done! Core unchanged.

## Implementation Details

### Collector Lifecycle

```
1. Registration: SaciServiceProvider boots
   → Collectors registered in CollectorRegistry

2. Request Start: SaciMiddleware receives request
   → registry->resetAll()
   → registry->startAll()
   → Collectors initialize (register listeners, etc)

3. Application Processing
   → Views render (ViewCollector tracks)
   → Logs fire (LogCollector captures)
   → etc.

4. Before Response: SaciMiddleware has response
   → Set request/response on collectors
   → registry->collectAll()
   → Collectors gather final data

5. Rendering: DebugBarInjector
   → Extract data from registry
   → Format for views
   → Inject into response

6. Terminate: After response sent
   → Late logs processed
```

### Data Flow

```php
// Old Way
$data = [
    'templates' => $tracker->getTemplates(),
    'resources' => $resources->getData(), // God object
];

// New Way (internal)
$data = [
    'templates' => $viewCollector->getData()['templates'],
    'resources' => [
        'request' => $requestCollector->getData()['request'],
        'response' => $requestCollector->getData()['response'],
        'route' => $routeCollector->getData(),
        'auth' => $authCollector->getData(),
        'logs' => $logCollector->getData()['logs'],
    ],
];
// But formatted to match old structure for backward compatibility!
```

## Testing Improvements

### Before
```php
// Hard to test - many dependencies
$tracker = new TemplateTracker($dumpManager, $storage);
$resources = new RequestResources(
    $dumpManager, $tracker, $logCollector,
    $logProcessor, $lateLogs, $pathResolver
);
// Now test everything together...
```

### After
```php
// Easy to test - isolated
$collector = new ViewCollector($tracker);
$collector->start();
$collector->collect();
$data = $collector->getData();
// Test one thing at a time!
```

## Performance Impact

- **Zero overhead** when collectors are disabled
- **Same performance** when all collectors enabled (same code, just organized)
- **Better memory** usage (collectors can be garbage collected after use)
- **Lazy evaluation** (collectors only run when needed)

## Code Quality Improvements

| Metric | Before | After |
|--------|--------|-------|
| RequestResources lines | 375 | N/A (split into 5 collectors) |
| Average collector size | N/A | ~80 lines each |
| Cyclomatic complexity | High (one big class) | Low (small classes) |
| Test coverage | Difficult | Easy (isolated units) |
| Extensibility | Hard (modify core) | Easy (add collectors) |

## Backward Compatibility

### Maintained

✅ All existing views work unchanged
✅ `RequestResources` still available (as adapter)
✅ Same data structure in views
✅ Same configuration keys
✅ Same public API

### Added (Non-Breaking)

✅ `CollectorRegistry` API
✅ Individual collector classes
✅ Per-collector configuration
✅ Extension points

### Deprecated (Still Works)

⚠️ `RequestResources` (use `CollectorRegistry` instead)
⚠️ Direct `TemplateTracker` usage (use `ViewCollector` instead)

## Future Roadmap

With the new architecture, these features are now trivial to add:

1. **DatabaseCollector**: Query tracking, N+1 detection
2. **HttpClientCollector**: External API call tracking
3. **CacheCollector**: Cache hit/miss tracking
4. **QueueCollector**: Job dispatch tracking
5. **MailCollector**: Email tracking
6. **EventCollector**: Event/listener tracking
7. **ExceptionCollector**: Exception tracking
8. **Custom Collectors**: User-defined collectors

Each takes ~50 lines of code and doesn't touch core!

## Questions?

- **Will this break my app?** No, 100% backward compatible
- **Do I need to update config?** No, but you can add collector flags
- **Do I need to republish assets?** No
- **Can I still use old API?** Yes, via adapter
- **When will old API be removed?** Not planned (adapter stays)

## Summary

This refactoring:
- ✅ Improves code quality and maintainability
- ✅ Enables easy extension with new collectors
- ✅ Maintains 100% backward compatibility
- ✅ Follows industry best practices
- ✅ Prepares for future features
- ✅ Has zero breaking changes

**Upgrade with confidence!** 🚀


