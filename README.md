# Saci 🔍

[![Tests](https://github.com/thiago-vieira/saci/actions/workflows/tests.yml/badge.svg)](https://github.com/thiago-vieira/saci/actions/workflows/tests.yml)
[![Code Coverage](https://codecov.io/gh/thiago-vieira/saci/branch/main/graph/badge.svg)](https://codecov.io/gh/thiago-vieira/saci)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x-FF2D20?logo=laravel)](https://laravel.com/)

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

### 6 Powerful Data Collectors

Each one is like a little spy that watches different parts of your app:

- 🎨 **Views**: Which Blade templates loaded? What data did they receive? How long did it take?
- 🌐 **Request**: Full HTTP request/response details (headers, body, cookies, session... the works)
- 🛣️ **Route**: Controller info, middleware stack, parameters, constraints
- 👤 **Auth**: Who's logged in? Which guard? User details
- 📝 **Logs**: All your `Log::info()`, `Log::error()`, etc. in one place
- 🗄️ **Database**: SQL queries with bindings, execution time, **N+1 detection**, duplicate finder, stack traces

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
# Preview Limits (shown inline before expanding)
SACI_PREVIEW_MAX_CHARS=70            # Max preview text length
SACI_PREVIEW_MAX_ITEMS=8             # Max array items in preview
SACI_PREVIEW_MAX_STRING=80           # Max string length in preview

# Full Dump Limits (lazy-loaded on click)
SACI_DUMP_MAX_DEPTH=5                # Max nesting depth for dumps
SACI_DUMP_MAX_ITEMS=10000            # Max array/object items to dump
SACI_DUMP_MAX_STRING=10000           # Max string length in dumps

# Storage Management
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
SACI_COLLECTOR_DATABASE=true         # SQL query tracking + N+1 detection
```

### Advanced Configuration

For edge cases and special requirements:

```env
# Content Security Policy (CSP)
SACI_CSP_NONCE=your-nonce-here       # Add CSP nonce to inline scripts (if using strict CSP)

# Asset Serving
SACI_FORCE_INTERNAL_ASSETS=false     # Force internal asset routes even if published
                                     # (useful for Docker/containerized environments)
```

**When to use these:**
- `SACI_CSP_NONCE`: If your app has a strict Content Security Policy that blocks inline scripts
- `SACI_FORCE_INTERNAL_ASSETS`: When published assets don't match package version (cache issues, container deployments)

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

## Database Tab 🗄️ - The Performance Detective

The Database collector is your first line of defense against slow queries and N+1 nightmares. It automatically watches every SQL query your app executes and gives you insights that would take hours to debug manually.

### What You Get

- **All Queries Listed**: Every single SQL statement, with bindings resolved
- **Execution Time**: See exactly how long each query took (slow queries > 100ms highlighted in orange)
- **N+1 Detection**: Automatically spots N+1 patterns (like running `SELECT * FROM users WHERE id = ?` 50 times)
- **Duplicate Finder**: Identifies queries that run multiple times (candidates for caching)
- **Stack Traces**: Click any query to see exactly where in your code it was called
- **Smart Filters**: Search queries, show only slow ones, filter by type (SELECT, INSERT, etc.)

### Real-World Example

Say you're loading a list of blog posts with their authors:

```php
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->author->name; // 💀 N+1 ALERT!
}
```

**Without Saci:** "Hmm, this page is slow. Wonder why? 🤔"

**With Saci Database Tab:**
```
⚠️ N+1 Queries Detected!
Pattern: SELECT * FROM users WHERE id = ?
Executed 47× (234ms total)
```

**Fix it:**
```php
$posts = Post::with('author')->all(); // Eager load, 2 queries total 🚀
```

### Use Cases

- **Find slow queries**: Sort by time, identify bottlenecks
- **Optimize N+1**: The tab literally tells you "hey, this is an N+1"
- **Reduce redundant queries**: See duplicates, add caching
- **Debug ORM issues**: See the actual SQL your Eloquent code generates
- **Production monitoring**: Keep it on in staging to catch issues before prod

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
