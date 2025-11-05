# Saci

Zero‑config, modern Laravel debug bar for Blade views, requests and routes.

## Install

```bash
composer require thiago-vieira/saci
```

Works out of the box. Set `SACI_ENABLED=true` to show the bar.
Publish assets once (recommended for CSP):

Optional (once):
```bash
php artisan vendor:publish --tag=saci-config
php artisan vendor:publish --tag=saci-assets
```

## Features

### Data Collection (Modular Architecture)
- **Views tab**: Loaded Blade views, variables with on‑demand dumps (Symfony VarDumper), loading times
- **Request tab**: Status, response time, method/URI, headers, body, query, cookies, session
- **Route tab**: Name, URI, methods, controller, middleware, parameters, constraints
- **Auth tab**: Current user, guard, authentication status
- **Logs tab**: Application logs with context, timestamps, levels

### UI/UX
- Persistent UI state (collapsed, height, per-card/per-variable; survives refresh)
- Resizable and accessible interface
- Multiple themes (default, dark, minimal)
- CSP‑friendly: external CSS/JS with inline fallbacks and optional nonce
- Shadow DOM isolation (no style conflicts)

### Performance
- **Modular collectors**: Enable/disable individual data collectors
- **Zero overhead** when collectors are disabled
- **Lazy loading**: Full dumps loaded on-demand
- **Memory limits**: Per-request caps to prevent memory issues
- **TTL cleanup**: Automatic cleanup of old dumps

## Configuration

### Basic Settings
```env
SACI_ENABLED=true                    # Enable/disable Saci globally
SACI_THEME=default                   # default|dark|minimal
SACI_TRANSPARENCY=0.85               # 0.0 to 1.0
SACI_TRACK_PERFORMANCE=true          # Track view loading times
SACI_ALLOW_AJAX=false                # Show bar on AJAX requests
SACI_ALLOW_IPS=127.0.0.1,::1         # IP whitelist (empty = allow all)
```

### Data Collection Limits
```env
SACI_PREVIEW_MAX_CHARS=70            # Preview string length
SACI_DUMP_MAX_ITEMS=10000            # Max items in dumps
SACI_DUMP_MAX_STRING=10000           # Max string length in dumps
SACI_PER_REQUEST_BYTES=1048576       # Max storage per request (1MB)
SACI_DUMP_TTL=60                     # Dump cleanup time (seconds)
```

### Collectors (Performance Tuning)

Enable/disable individual data collectors. **When disabled, the collector has zero overhead**.

```env
SACI_COLLECTOR_VIEWS=true            # Blade views tracking
SACI_COLLECTOR_REQUEST=true          # HTTP request/response metadata
SACI_COLLECTOR_ROUTE=true            # Route and controller info
SACI_COLLECTOR_AUTH=true             # Authentication data
SACI_COLLECTOR_LOGS=true             # Application logs
```

**Use cases:**
- **Production debugging**: Disable heavy collectors (logs, views) but keep request/route
- **API-only apps**: Disable views collector
- **Performance**: Disable collectors you don't need for specific debugging sessions
- **Privacy**: Disable auth collector if not needed

## Performance Optimization

### Disable Collectors You Don't Need

Each collector can be disabled independently for optimal performance:

```env
# Example: API-only app (no views)
SACI_COLLECTOR_VIEWS=false

# Example: Production debugging (lightweight)
SACI_COLLECTOR_LOGS=false
SACI_COLLECTOR_AUTH=false

# Example: Only need route debugging
SACI_COLLECTOR_VIEWS=false
SACI_COLLECTOR_REQUEST=false
SACI_COLLECTOR_AUTH=false
SACI_COLLECTOR_LOGS=false
SACI_COLLECTOR_ROUTE=true
```

**Performance impact:**
- Disabled collectors: **Zero overhead** (not instantiated, not executed)
- Enabled collectors: Minimal overhead (optimized data collection)

### Memory Management

Prevent memory issues with built-in limits:

```env
SACI_PER_REQUEST_BYTES=1048576       # 1MB per request (adjust as needed)
SACI_DUMP_TTL=60                     # Cleanup after 60 seconds
```

## Architecture

Saci uses the **Collector Pattern** (same as Symfony Profiler, Laravel Telescope):

```
CollectorRegistry
  ├── ViewCollector (tracks Blade views)
  ├── RequestCollector (HTTP metadata)
  ├── RouteCollector (routing info)
  ├── AuthCollector (authentication)
  └── LogCollector (application logs)
```

**Benefits:**
- Clean separation of concerns
- Easy to extend with custom collectors
- Testable and maintainable
- Industry-standard architecture

See `ARCHITECTURE.md` for details.

## Extending Saci

Want to track queries, cache, or custom data? Create a collector!

```php
use ThiagoVieira\Saci\Collectors\BaseCollector;

class DatabaseCollector extends BaseCollector
{
    public function getName(): string { return 'database'; }
    public function getLabel(): string { return 'Database'; }

    protected function doStart(): void {
        DB::listen(fn($query) => $this->queries[] = $query);
    }

    protected function doCollect(): void {
        $this->data = ['queries' => $this->queries];
    }
}
```

Register in `SaciServiceProvider`:
```php
$registry->register($app->make(DatabaseCollector::class));
```

See `src/Collectors/README.md` for detailed guide.

## License

MIT