# Saci 🔍

> A modern, zero-config Laravel debug bar that actually makes debugging fun (yes, really).

Track your Blade views, inspect requests, and peek into routes with a sleek, persistent UI that won't mess with your styles. Think of it as X-ray vision for your Laravel app.

## Quick Start

Install it:
```bash
composer require thiago-vieira/saci
```

Enable it:
```env
SACI_ENABLED=true
```

That's it! 🎉 Saci works out of the box. No config files, no setup, no headaches.

**Optional** (but recommended for CSP):
```bash
php artisan vendor:publish --tag=saci-config
php artisan vendor:publish --tag=saci-assets
```

## What's in the Box? 📦

### 5 Powerful Data Collectors

Each one is like a little spy that watches different parts of your app:

- 🎨 **Views**: Which Blade templates loaded? What data did they receive? How long did it take?
- 🌐 **Request**: Full HTTP request/response details (headers, body, cookies, session... the works)
- 🛣️ **Route**: Controller info, middleware stack, parameters, constraints
- 👤 **Auth**: Who's logged in? Which guard? User details
- 📝 **Logs**: All your `Log::info()`, `Log::error()`, etc. in one place

### UI That Doesn't Suck

- **Persistent state**: Collapsed/expanded settings survive page refresh (because nobody likes clicking the same thing 47 times)
- **Resizable**: Drag it bigger or smaller, your call
- **Themes**: Default, dark, or minimal (for the minimalists)
- **CSP-friendly**: Works with Content Security Policy (because security matters)
- **Isolated styles**: Scoped CSS to avoid conflicts with your app styles

### Performance? We Got You

- **Zero overhead when disabled**: Seriously, zero. Not "almost zero", actual zero
- **Lazy loading**: Full dumps only load when you click (fast initial page load)
- **Memory limits**: Won't blow up your app even with massive arrays
- **Modular**: Turn off collectors you don't need (more on this below)

## Configuration 🎛️

### The Basics

```env
SACI_ENABLED=true                    # The master switch
SACI_THEME=default                   # default | dark | minimal
SACI_TRANSPARENCY=0.85               # 0.0 (invisible) to 1.0 (solid)
SACI_TRACK_PERFORMANCE=true          # Measure view loading times
SACI_ALLOW_AJAX=false                # ⚠️ Keep false! (breaks AJAX responses)
SACI_ALLOW_IPS=127.0.0.1,::1         # IP whitelist (empty = everyone)
```

### Data Collection Limits

```env
SACI_PREVIEW_MAX_CHARS=70            # How long previews should be
SACI_DUMP_MAX_ITEMS=10000            # Max array/object items to dump
SACI_DUMP_MAX_STRING=10000           # Max string length in dumps
SACI_PER_REQUEST_BYTES=1048576       # Max storage per request (1MB)
SACI_DUMP_TTL=60                     # Auto-cleanup old dumps (seconds)
```

### Performance Tuning 🚀

Here's the cool part: each collector can be toggled on/off independently. **When off, it has ZERO overhead**. Not "almost zero", actual zero. Not instantiated, not executed, not even a tiny bit of memory used.

> **⚠️ About AJAX requests:** Keep `SACI_ALLOW_AJAX=false` unless you know what you're doing. Enabling it will inject the debug bar into AJAX responses, which will break your frontend if it expects JSON or partial HTML. Only enable for debugging full-page AJAX loads (Turbo, Livewire, etc).

```env
SACI_COLLECTOR_VIEWS=true            # Blade view tracking
SACI_COLLECTOR_REQUEST=true          # HTTP request/response
SACI_COLLECTOR_ROUTE=true            # Route & controller info
SACI_COLLECTOR_AUTH=true             # Authentication data
SACI_COLLECTOR_LOGS=true             # Application logs
```

#### Real-World Examples

**Debugging an API?** Views are useless:
```env
SACI_COLLECTOR_VIEWS=false           # Skip it, save ~30% overhead
```

**Need to debug in production?** Go lightweight:
```env
SACI_COLLECTOR_LOGS=false            # Logs can be heavy
SACI_COLLECTOR_AUTH=false            # Don't need user info
# Keep request + route for the actual debugging
```

**Only care about routing issues?** Laser focus:
```env
SACI_COLLECTOR_VIEWS=false
SACI_COLLECTOR_REQUEST=false
SACI_COLLECTOR_AUTH=false
SACI_COLLECTOR_LOGS=false
SACI_COLLECTOR_ROUTE=true            # Just this one, please
```

**Performance impact:**
- ❌ Disabled = Zero overhead (literally not even loaded)
- ✅ Enabled = Minimal overhead (smart, optimized collection)

### Memory Management

Because nobody likes memory leaks:

```env
SACI_PER_REQUEST_BYTES=1048576       # Cap per request (tweak if needed)
SACI_DUMP_TTL=60                     # Old dumps? Gone. (in 60s)
```

## Architecture (for the Nerds) 🤓

Saci uses the **Collector Pattern** - the same battle-tested approach as Symfony Profiler and Laravel Telescope.

```
CollectorRegistry (the boss)
  ├── ViewCollector (watches Blade)
  ├── RequestCollector (watches HTTP)
  ├── RouteCollector (watches routing)
  ├── AuthCollector (watches users)
  └── LogCollector (watches logs)
```

**Why this is awesome:**
- Each collector = one job (single responsibility)
- Add new collectors without touching the core (open/closed principle)
- Super easy to test (isolated units)
- Industry-standard pattern (proven in production)

Want to know more? Check `ARCHITECTURE.md` for the deep dive.

## Want to Extend It? 🔧

Got custom tracking needs? Create your own collector in ~50 lines:

```php
use ThiagoVieira\Saci\Collectors\BaseCollector;

class DatabaseCollector extends BaseCollector
{
    public function getName(): string { return 'database'; }
    public function getLabel(): string { return 'Database'; }

    protected function doStart(): void {
        // Start listening for queries
        DB::listen(fn($query) => $this->queries[] = $query);
    }

    protected function doCollect(): void {
        // Store collected data
        $this->data = [
            'queries' => $this->queries,
            'total_time' => array_sum(array_column($this->queries, 'time')),
        ];
    }
}
```

Register it in `SaciServiceProvider`:
```php
$registry->register($app->make(DatabaseCollector::class));
```

Boom! Your custom collector is now part of Saci. No core changes needed.

Want to build something cool? Full guide at `src/Collectors/README.md`.

## Why "Saci"? 🤔

Named after [Saci](https://en.wikipedia.org/wiki/Saci_(Brazilian_folklore)) from Brazilian folklore - a one-legged trickster who knows everything happening in the forest. Like our debug bar, he sees everything that's going on.

Plus, "Saci" is fun to say. Try it: *Sah-see*. See? Fun.

## License

MIT - Go wild, just don't blame us if it reads your mind 😉
