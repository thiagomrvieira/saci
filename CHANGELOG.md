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


