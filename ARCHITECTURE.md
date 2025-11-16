# Saci Architecture

## Overview

Saci follows Laravel's best practices and modern PHP patterns, implementing a clean, modular architecture with proper separation of concerns. The architecture is based on the **Collector Pattern**, inspired by Symfony Profiler, Laravel Telescope, and Clockwork.

## Architecture Principles

1. **Collector Pattern**: Each data type has its own dedicated collector
2. **Single Responsibility**: Each class has one well-defined purpose
3. **Open/Closed Principle**: Easy to extend with new collectors without modifying core
4. **Dependency Injection**: All dependencies injected via constructor
5. **Interface Segregation**: Collectors implement a common interface

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────┐
│                   Presentation Layer                        │
│  (Blade Views, JavaScript, CSS)                             │
└─────────────────────────────────────────────────────────────┘
                            ↑
┌─────────────────────────────────────────────────────────────┐
│                   Orchestration Layer                       │
│  • SaciMiddleware (coordinates collectors)                  │
│  • DebugBarInjector (renders output)                        │
│  • RequestValidator (guards execution)                      │
└─────────────────────────────────────────────────────────────┘
                            ↑
┌─────────────────────────────────────────────────────────────┐
│                   Collection Layer                          │
│  • CollectorRegistry (manages collectors)                   │
│  • ViewCollector, RequestCollector, RouteCollector, etc.    │
└─────────────────────────────────────────────────────────────┘
                            ↑
┌─────────────────────────────────────────────────────────────┐
│                   Support Layer                             │
│  • DumpManager, DumpStorage, LogProcessor, etc.             │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### Orchestration Layer

#### **SaciServiceProvider**
- **Responsibility**: Package bootstrapping and service registration
- **Features**:
  - Configuration merging
  - View loading
  - Middleware registration
  - Collector registration via CollectorRegistry
  - Service container bindings
  - Configuration publishing

#### **SaciMiddleware**
- **Responsibility**: Request lifecycle orchestrator
- **Features**:
  - Validates if tracing should occur
  - Resets and starts all collectors
  - Sets request/response on collectors
  - Triggers data collection
  - Handles late logs (terminable middleware)
- **Dependencies**: CollectorRegistry, DebugBarInjector, RequestValidator

#### **DebugBarInjector**
- **Responsibility**: Response modification and debug bar rendering
- **Features**:
  - HTML content injection (skips non-HTML/binary/attachment)
  - Extracts data from collectors via registry
  - Formats data for view compatibility
  - View rendering
  - Error handling
- **Dependencies**: CollectorRegistry

#### **RequestValidator**
- **Responsibility**: Request validation logic
- **Features**:
  - Gating via `saci.enabled` (inherits app.debug when null)
  - Skips ajax when disabled, skips JSON accept, IP allowlist
  - Skips BinaryFileResponse/StreamedResponse
  - Skips Saci's own routes (recursive injection prevention)

#### **SaciConfig**
- **Responsibility**: Configuration management
- **Features**:
  - Centralized configuration access
  - Type-safe configuration methods
  - Default value management
  - Collector enable/disable flags

#### **SaciInfo**
- **Responsibility**: Package metadata
- **Features**:
  - Version information
  - Author information
  - Package constants

### Collection Layer

#### **CollectorRegistry**
- **Responsibility**: Central registry for all collectors
- **Features**:
  - Register collectors
  - Get collector by name
  - Start/collect/reset all collectors
  - Filter enabled collectors
  - Aggregate data from all collectors

#### **CollectorInterface**
- **Contract for all collectors**
- **Methods**:
  - `getName()`: Unique identifier
  - `getLabel()`: Display label
  - `start()`: Initialize collection
  - `collect()`: Gather data
  - `getData()`: Return collected data
  - `isEnabled()`: Check if enabled
  - `reset()`: Clean up for next request

#### **BaseCollector**
- **Abstract base class for collectors**
- **Features**:
  - Implements common collector logic
  - Enable/disable via config
  - Template method pattern (doStart, doCollect, doReset)
  - Protected data storage

#### **ViewCollector**
- **Responsibility**: Collects Blade view data
- **Delegates to**: TemplateTracker
- **Data**: Templates, total count, request ID

#### **RequestCollector**
- **Responsibility**: Collects HTTP request/response metadata
- **Data**: Method, URL, headers, body, query, cookies, session, duration

#### **RouteCollector**
- **Responsibility**: Collects route and controller information
- **Data**: Route name, URI, methods, controller, middleware, parameters

#### **AuthCollector**
- **Responsibility**: Collects authentication data
- **Data**: Guard, authenticated status, user ID, email, name

#### **LogCollector**
- **Responsibility**: Collects application logs
- **Delegates to**: Support\LogCollector, LogProcessor
- **Data**: Log entries with level, message, context, timestamp

#### **DatabaseCollector**
- **Responsibility**: Collects SQL queries with performance analysis
- **Features**:
  - N+1 query pattern detection
  - Duplicate query identification
  - Slow query highlighting (> 100ms)
  - Stack trace for each query
  - Binding resolution and formatting
- **Data**: Query list, execution times, connections, slow queries, duplicates, N+1 patterns
- **Dependencies**: Laravel's QueryExecuted event listener

### Legacy Adapters

#### **TemplateTracker**
- **Kept for backward compatibility and specialized view tracking**
- **Features**:
  - View creator/composer registration
  - Template path extraction
  - Data filtering and sanitization
  - Performance tracking
  - Dump management

#### **RequestResourcesAdapter**
- **Backward compatibility adapter**
- **Delegates to**: CollectorRegistry and individual collectors
- **Deprecated**: Use CollectorRegistry directly

## Design Patterns

### 1. **Dependency Injection**
- All dependencies are injected through constructors
- Services are registered as singletons in the service container
- Follows Laravel's IoC container patterns

### 2. **Single Responsibility Principle**
- Each class has a single, well-defined responsibility
- Clear separation between tracking, validation, and injection logic

### 3. **Configuration Pattern**
- Centralized configuration management
- Type-safe configuration access
- Environment-based configuration

### 4. **Service Container Pattern**
- Proper Laravel service registration
- Singleton pattern for shared resources
- Automatic dependency resolution

## Data Flow

```
Request → Middleware → RequestValidator → CollectorRegistry
                                              ↓
                                    [Start All Collectors]
                                              ↓
                                    [Set Request/Response]
                                              ↓
                           Application Processing (Views, Routes, etc.)
                                              ↓
                                    [Collect All Data]
                                              ↓
                                    DebugBarInjector
                                              ↓
                                    Response with Debug Bar
```

1. **Request** enters `SaciMiddleware`
2. **RequestValidator** determines if tracing should occur
3. **CollectorRegistry** resets and starts all enabled collectors
4. **Request/Response** is set on collectors that need it
5. **Application processes** the request (views, controllers, etc.)
6. **CollectorRegistry** triggers data collection from all collectors
7. **DebugBarInjector** extracts data from registry and renders debug bar
8. **Response** is returned with debug bar injected
9. **Terminate**: Late logs are processed (after response sent)

## Configuration Structure (excerpt)

```php
'saci' => [
    'enabled' => env('SACI_ENABLED', null),
    'auto_register_middleware' => true,
    'allow_ajax' => false,
    'allow_ips' => [],
    'hide_data_fields' => ['password','token','secret','api_key','credentials'],
    'mask_keys' => ['password','/authorization/i','/cookie/i'],
    'ui' => [
        'position' => 'bottom',
        'theme' => 'default',
        'max_height' => '30vh',
        'transparency' => 1.0,
    ],
    'dump' => [
        'preview_max_chars' => 70,
        'max_items' => 10000,
        'max_string' => 10000,
    ],
]
```

## Error Handling

- Graceful error handling in all components
- Logging for debugging purposes
- Fallback mechanisms for failed operations
- Non-intrusive error recovery

## Performance & Security

- On-demand dumps via Symfony VarDumper; previews only in initial HTML
- Per-request storage with caps; lazy load full dumps via `/__saci/dump/{requestId}/{dumpId}`
- Hard caps on preview/dump size; masking for sensitive keys
- CSP-friendly assets with optional nonce; no inline JS required

## Testing Strategy

- Unit tests for each component
- Integration tests for middleware
- Configuration testing
- Error scenario testing

## Adding New Collectors

Adding a new collector is straightforward:

1. **Create collector class** extending `BaseCollector`
2. **Implement required methods** (getName, getLabel, doCollect)
3. **Register in ServiceProvider** via CollectorRegistry
4. **Add config option** in `saci.php` (optional)
5. **Create view template** (optional)

See `src/Collectors/README.md` for detailed guide.

## Future Enhancements

- **Additional Collectors**:
  - HttpClientCollector (external API calls)
  - CacheCollector (cache operations)
  - QueueCollector (dispatched jobs)
  - MailCollector (sent emails)
  - EventCollector (fired events)
  - ExceptionCollector (caught exceptions)
- **Analyzers Layer**: Post-processing for performance insights
- **Timeline View**: Waterfall chart of request lifecycle
- **Export/Import**: Save and share debug sessions
- **API**: Programmatic access to collected data