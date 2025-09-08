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


