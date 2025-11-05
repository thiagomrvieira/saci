# Saci Architecture - Visual Guide

## 📁 New Directory Structure

```
saci/
├── src/
│   ├── Collectors/                    [NEW LAYER]
│   │   ├── Contracts/
│   │   │   └── CollectorInterface.php
│   │   ├── BaseCollector.php
│   │   ├── ViewCollector.php
│   │   ├── RequestCollector.php
│   │   ├── RouteCollector.php
│   │   ├── AuthCollector.php
│   │   ├── LogCollector.php
│   │   └── README.md
│   │
│   ├── Support/
│   │   ├── CollectorRegistry.php      [NEW]
│   │   ├── DumpManager.php
│   │   ├── DumpStorage.php
│   │   ├── LogCollector.php           [Support layer]
│   │   ├── LogProcessor.php
│   │   ├── LateLogsPersistence.php
│   │   ├── FilePathResolver.php
│   │   └── PerformanceFormatter.php
│   │
│   ├── Http/Controllers/
│   │   ├── DumpController.php
│   │   └── AssetsController.php
│   │
│   ├── Resources/
│   │   ├── config/saci.php            [UPDATED]
│   │   ├── views/...
│   │   └── assets/...
│   │
│   ├── SaciServiceProvider.php        [UPDATED]
│   ├── SaciMiddleware.php             [UPDATED]
│   ├── DebugBarInjector.php           [UPDATED]
│   ├── RequestValidator.php
│   ├── TemplateTracker.php            [Legacy - still used]
│   ├── RequestResources.php           [Alias to Adapter]
│   ├── RequestResourcesAdapter.php    [NEW - Backward compat]
│   ├── SaciConfig.php
│   └── SaciInfo.php
│
├── ARCHITECTURE.md                     [UPDATED]
├── REFACTORING.md                      [NEW]
├── REFACTORING_SUMMARY.md              [NEW]
└── CHANGELOG.md
```

## 🔄 Request Flow Diagram

```
┌──────────────────────────────────────────────────────────────┐
│  HTTP Request                                                │
└──────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  SaciMiddleware                                              │
│  • Check if should trace (RequestValidator)                  │
│  • Reset all collectors                                      │
└──────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  CollectorRegistry::startAll()                               │
│                                                              │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐              │
│  │   Views    │  │  Request   │  │   Route    │              │
│  │ Collector  │  │ Collector  │  │ Collector  │              │
│  └─────┬──────┘  └─────┬──────┘  └─────┬──────┘              │
│        │               │               │                     │
│        └───────────────┴───────────────┘                     │
│                        │                                     │
│  ┌────────────┐  ┌────────────┐                              │
│  │    Auth    │  │    Logs    │                              │
│  │ Collector  │  │ Collector  │                              │
│  └─────┬──────┘  └─────┬──────┘                              │
│        │               │                                     │
│        └───────────────┘                                     │
│                │                                             │
│           [Register listeners,                               │
│            Initialize tracking]                              │
└────────────────┬─────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────┐
│  Laravel Application Processing                              │
│  • Controllers execute                                       │
│  • Views render → ViewCollector tracks                       │
│  • Logs fire → LogCollector captures                         │
│  • Routes resolve → RouteCollector observes                  │
└──────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  Response Generated                                          │
│  • Set Request/Response on collectors                        │
└──────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  CollectorRegistry::collectAll()                             │
│                                                              │
│  Each collector gathers final data:                          │
│  • ViewCollector → templates array                           │
│  • RequestCollector → request/response metadata              │
│  • RouteCollector → route info                               │
│  • AuthCollector → user data                                 │
│  • LogCollector → log entries                                │
└──────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  DebugBarInjector                                            │
│  • Extract data from CollectorRegistry                       │
│  • Format for view compatibility                             │
│  • Render blade template                                     │
│  • Inject into response HTML                                 │
└──────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  HTTP Response (with Debug Bar)                              │
└──────────────────┬───────────────────────────────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│  Middleware::terminate()                                     │
│  • Process late logs (after response sent)                   │
└──────────────────────────────────────────────────────────────┘
```

## 🎯 Collector Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│  COLLECTOR LIFECYCLE                                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. REGISTRATION (Boot Time)                                │
│     SaciServiceProvider::boot()                             │
│     └─> CollectorRegistry::register(collector)              │
│                                                             │
│  2. START (Request Begin)                                   │
│     SaciMiddleware::handle()                                │
│     └─> registry->startAll()                                │
│         └─> collector->start()                              │
│             └─> doStart() [Template Method]                 │
│                 • Register event listeners                  │
│                 • Initialize state                          │
│                                                             │
│  3. OBSERVE (During Request)                                │
│     Application processes request                           │
│     └─> Collectors passively observe:                       │
│         • View renders → ViewCollector                      │
│         • Logs fire → LogCollector                          │
│         • Queries execute → [Future: DatabaseCollector]     │
│                                                             │
│  4. COLLECT (Before Response)                               │
│     SaciMiddleware::handle()                                │
│     └─> registry->collectAll()                              │
│         └─> collector->collect()                            │
│             └─> doCollect() [Template Method]               │
│                 • Finalize data gathering                   │
│                 • Store in $this->data                      │
│                                                             │
│  5. RENDER (Response Modification)                          │
│     DebugBarInjector::inject()                              │
│     └─> registry->getAllData()                              │
│         └─> collector->getData()                            │
│             • Returns collected data array                  │
│                                                             │
│  6. RESET (For Next Request)                                │
│     SaciMiddleware::handle()                                │
│     └─> registry->resetAll()                                │
│         └─> collector->reset()                              │
│             └─> doReset() [Template Method]                 │
│                 • Clear state                               │
│                 • Prepare for reuse                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## 🧩 Collector Pattern

```
┌─────────────────────────────────────────────────────────────┐
│  CollectorInterface (Contract)                              │
├─────────────────────────────────────────────────────────────┤
│  + getName(): string                                        │
│  + getLabel(): string                                       │
│  + start(): void                                            │
│  + collect(): void                                          │
│  + getData(): array                                         │
│  + isEnabled(): bool                                        │
│  + reset(): void                                            │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        │ implements
                        ▼
┌─────────────────────────────────────────────────────────────┐
│  BaseCollector (Abstract)                                   │
├─────────────────────────────────────────────────────────────┤
│  # isCollecting: bool                                       │
│  # data: array                                              │
│  + start(): void                                            │
│  + collect(): void                                          │
│  + getData(): array                                         │
│  + isEnabled(): bool                                        │
│  + reset(): void                                            │
│  # doStart(): void [Hook]                                   │
│  # doCollect(): void [Hook]                                 │
│  # doReset(): void [Hook]                                   │
└───────────────────────┬─────────────────────────────────────┘
                        │
          ┌─────────────┼─────────────┬─────────────┬─────────┐
          │             │             │             │         │
          ▼             ▼             ▼             ▼         ▼
    ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────┐  ┌──────┐
    │  View    │  │ Request  │  │  Route   │  │ Auth │  │ Logs │
    │Collector │  │Collector │  │Collector │  │Coll. │  │Coll. │
    └──────────┘  └──────────┘  └──────────┘  └──────┘  └──────┘
```

## 📊 Data Structure

```
CollectorRegistry::getAllData()
│
├── ['views'] → ViewCollector::getData()
│   └── {
│       templates: [...],
│       total: 5,
│       request_id: 'uuid'
│     }
│
├── ['request'] → RequestCollector::getData()
│   └── {
│       request: {
│         method: 'GET',
│         full_url: '...',
│         headers: {...},
│         ...
│       },
│       response: {
│         status: 200,
│         duration_ms: 123.45,
│         ...
│       }
│     }
│
├── ['route'] → RouteCollector::getData()
│   └── {
│       name: 'home',
│       uri: '/',
│       controller: 'HomeController',
│       middleware: [...],
│       ...
│     }
│
├── ['auth'] → AuthCollector::getData()
│   └── {
│       guard: 'web',
│       authenticated: true,
│       id: 1,
│       ...
│     }
│
└── ['logs'] → LogCollector::getData()
    └── {
        logs: [
          {level: 'info', message: '...', ...},
          ...
        ]
      }
```

## 🔌 Extension Example

```
Want to add Database Tracking?

1. Create DatabaseCollector.php (50 lines)
   ┌─────────────────────────────────────┐
   │ class DatabaseCollector             │
   │   extends BaseCollector             │
   │ {                                   │
   │   protected function doStart() {    │
   │     DB::listen($this->logQuery);    │
   │   }                                 │
   │   protected function doCollect() {  │
   │     $this->data = $this->queries;   │
   │   }                                 │
   │ }                                   │
   └─────────────────────────────────────┘

2. Register (1 line in ServiceProvider)
   $registry->register(DatabaseCollector::class);

3. Add config (optional)
   'collectors' => [
     'database' => true,
   ]

4. Create view (optional)
   views/partials/database-tab.blade.php

5. DONE! Core unchanged. ✅
```

## ✨ Before vs After

### Before (Monolithic)
```
RequestResources.php (400+ lines)
├── collectFromRequest()
│   ├── Route info
│   ├── Request metadata
│   ├── Auth data
│   └── Response info
├── processLogs()
└── getData()

Problem: Everything in one class!
```

### After (Modular)
```
Collectors/
├── ViewCollector.php (~80 lines)
├── RequestCollector.php (~150 lines)
├── RouteCollector.php (~120 lines)
├── AuthCollector.php (~50 lines)
└── LogCollector.php (~80 lines)

Solution: Each concern isolated!
```

## 🎓 Key Takeaways

1. **Collector Pattern** = Industry standard (Symfony, Telescope)
2. **Single Responsibility** = Each collector does one thing
3. **Open/Closed** = Extend without modifying core
4. **Registry Pattern** = Central management
5. **Template Method** = Consistent lifecycle hooks
6. **100% Backward Compatible** = Zero breaking changes

---

**Architecture is now clean, testable, and ready for growth!** 🚀


