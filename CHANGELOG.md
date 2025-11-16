# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [1.1.0] - 2025-09-08
### Added
- Alpine.js integration (no build):
  - Collapsible bar; arrow indicator; keyboard accessibility (Enter/Space).
  - Per-view cards toggle; global Expand/Collapse.
- Variables view: type, preview, pretty-printed JSON (wrapped), truncation limits.
- Themes: `default`, `dark`, `minimal` (see Changes for mapping).
- Transparency control for the whole bar via `SACI_TRANSPARENCY` (0.0–1.0).
- External CSS (`public/vendor/saci/css/saci.css`) with reusable classes and `[x-cloak]` handling.

### Changed
- UI refinements: modern cards, sticky Actions column, subtler gradients, better spacing and badges.
- Blade cleanup: split into partials (`header`, `template-card`, `variables-table`, `scripts`), attributes on separate lines.
- Inline styles migrated to CSS; small JS replaced by declarative Alpine in markup.
- Performance display: hide duration entirely when `SACI_TRACK_PERFORMANCE=false`.
- Response typing: use Symfony Response to support `RedirectResponse`.

### Migration/Upgrade Notes
- Publish assets (once): `php artisan vendor:publish --tag=saci-assets`.
- Update `.env` as needed:
  - `SACI_THEME=default|dark|minimal`
  - `SACI_TRANSPARENCY=0.85` (exemplo)
- If values in `.env` não refletirem: `php artisan config:clear && php artisan cache:clear`.

## [1.0.0] - 2025-01-01
### Added
- Initial release.

## [2.0.0] - 2025-10-05
### Changed
- Require PHP >= 8.0 (typed properties, `str_contains`).
- Refined UI styles: reduced border radii, softer shadows, cleaner cards.
- Alpine-first JS refactor: component + store, modular partials.
- Drag-to-resize header with persisted height; smoother card animations.
- Persistent UI state across reloads (collapsed, height, per-card and per-variable).

### Fixed
- Avoid toggle after drag (click-guard) and restore height after expand.
- Show resize cursor only when expanded.

### Notes
- Assets publish remains optional; inline CSS fallback ensures zero-touch install.
- Recommended to republish assets to get the latest CSS: `php artisan vendor:publish --tag=saci-assets --force`.


## [2.0.1] - 2025-10-12
### Fixed
- Ensure inline CSS is rendered unescaped in `saci::bar` to avoid broken styles when assets are not published.
- Add server-side default classes for theme and position to stabilize initial render before Alpine initializes.

## [2.0.2] - 2025-10-12
### Changed
- Visibility rule: the bar now renders whenever `SACI_ENABLED=true`, independent of `APP_ENV`.

### Notes
- Consider the security implications before enabling in production; sensitive field filtering remains in place.
- No breaking changes. Safe patch update.

## [2.1.0] - 2025-10-12
### Added
- Tabbed UI: header now has tabs for "Views" and "Resources" with persisted selection.
- Resources tab: shows route details (name, uri, methods, controller) and resolved services per request.

### Changed
- Inject `RequestResources` via DI and pass collected data to the bar view.

### Notes
- Backward compatible; if you were on 2.0.x, simply update.

## [2.2.0] - 2025-10-12
### Added
- Request tab renamed and expanded: response time, method/URI, headers/body/query/cookies/session with on‑demand dumps.
- Views tab summary moved to header controls: "X views loaded in: Y ms".
- Route tab added: name, uri, methods, domain, prefix, controller/action; dumps for parameters, middleware, where, compiled.

### Changed
- Tabs/start state: bar starts collapsed unless user persisted state; per-card and per-variable expand state persists across reloads (Views/Request/Route).
- UI polish: professional tab styles and aligned summaries; header controls show context summary for the active tab.

### Fixed
- First-click toggle issue on Request cards by unifying state and persistence.
- Avoid duplicate injection on dump AJAX responses; guard for existing bar; skip Saci internal routes.
- Restore dumps after refresh for expanded rows (including inline dumps in Request/Route).


## [2.3.0] - 2025-10-19
### Added
- On-demand, CSP-friendly dumps for Request (headers/body/query/cookies/session) and Route (parameters/middleware/where/compiled), using Symfony VarDumper + storage per request.
- Inline JS/CSS fallbacks and route `/__saci/dump/{requestId}/{dumpId}` for lazy loading dumps.
- Header controls show contextual summaries (Views: “X views loaded in”; Request: “Response time”; Route: “METHOD URI”), both expanded and collapsed.

### Changed
- Alpine-first refactor of `saci.js`: smaller, focused methods; helpers for restore/toggle/load; persisted expand state across tabs (Views/Request/Route).
- Tabs: removed counts from titles; minimized/expanded summaries moved to header area.

### Removed
- Global Expand/Collapse buttons and related code/styling.

### Fixed
- Prevent duplicate bar injection (guards for existing bar and Saci internal routes).
- Restore expanded variable dumps after refresh (including inline sections in Request/Route).

## [2.4.0] - 2025-11-16
### Added
- **DatabaseCollector**: Comprehensive SQL query tracking and performance analysis
  - All executed queries with bindings, execution time, and connection info
  - **N+1 Detection**: Automatically identifies N+1 query patterns (3+ similar queries)
  - **Duplicate Finder**: Highlights queries executed multiple times
  - **Slow Query Highlighting**: Flags queries taking > 100ms in orange
  - **Stack Traces**: Shows exactly where each query was called from your code
  - **Smart Filters**: Search queries, filter by type (SELECT/INSERT/UPDATE/DELETE), show only slow queries
- Database tab with professional UI matching existing tabs
- Query bindings toggle for detailed parameter inspection
- N+1 pattern examples with expandable details
- Duplicate queries summary section
- Database statistics in header (query count, total time, N+1 alerts)

### Configuration
```env
SACI_COLLECTOR_DATABASE=true   # Enable/disable database collector (default: true)
```

### Performance
- Zero overhead when collector is disabled
- Efficient event listeners using Laravel's QueryExecuted event
- Smart filtering with virtual scrolling for large query lists
- Configurable thresholds via class constants

### Notes
- Detects N+1 patterns by normalizing queries and identifying repetitions (≥3 similar queries)
- Provides actionable insights with code locations for easy debugging
- Integrates seamlessly with existing Saci UI and themes

