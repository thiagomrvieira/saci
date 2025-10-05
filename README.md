# Saci

A modern, elegant Laravel debugger that shows loaded views and their data in a floating bar.

## Requirements

- PHP >= 8.0
- Laravel 9–12

## Installation

1. Install the package via Composer:
```bash
composer require thiago-vieira/saci
```

Saci works immediately after installation. If you do not publish the assets, the CSS will be served inline automatically as a fallback.

2. Publish the configuration file (optional):
```bash
php artisan vendor:publish --tag=saci-config
```

3. Publish the assets (CSS) for the debug bar UI (optional but recommended for production/cache/CDN):
```bash
php artisan vendor:publish --tag=saci-assets
```
This publishes the CSS to `public/vendor/saci/css/saci.css`.

If you update the package and need the latest CSS, republish with force:
```bash
php artisan vendor:publish --tag=saci-assets --force
```

## Configuration

The package is automatically configured, but you can customize the settings by editing the `config/saci.php` file:

```php
return [
    'enabled' => env('SACI_ENABLED', true),
    'auto_register_middleware' => true,
    'environments' => ['local', 'development'],
    'hide_data_fields' => ['password', 'token', 'secret', 'api_key', 'credentials'],
    'ui' => [
        'position' => 'bottom',
        'theme' => env('SACI_THEME', 'default'), // 'default' (ex-dark), 'dark' (ex-minimal), 'minimal'
        'max_height' => '30vh'
    ],
    'track_performance' => env('SACI_TRACK_PERFORMANCE', true),
    // Dump/preview normalization (limits to keep the UI fast and readable)
    'dump' => [
        'max_depth' => 5,
        'max_items' => 10,
        'max_string_length' => 200,
    ],
];
```

## Usage

After installation, Saci will be automatically activated in development environments. It will show a floating bar at the bottom of the page with information about loaded views.
You can drag the bar header to resize its height. The bar height, collapsed/expanded state, and the open state of each view and variable are persisted across reloads (via localStorage).

### Features

- Shows all loaded Blade views
- Displays variables (type, preview and safe pretty-printed values)
- **View loading time tracking** (performance monitoring)
- Responsive and collapsible interface
- Resizable debug bar (drag-to-resize)
- Persistent UI state across reloads (collapsed, bar height, per-card and per-variable)
- Configurable for different environments
- Sensitive data protection

### Environment Variables

```env
SACI_ENABLED=true
SACI_TRACK_PERFORMANCE=true
# Theme options
# - default
# - dark
# - minimal
SACI_THEME=default
SACI_TRANSPARENCY=0.85
```

### Performance Tracking

Saci includes built-in performance monitoring that tracks the loading time of each view:

- **Individual view timing**: Each view shows its loading time in milliseconds
- **Total timing**: The main bar displays the total loading time for all views
- **Configurable**: Can be enabled/disabled via `SACI_TRACK_PERFORMANCE` environment variable

Example output:
![Saci Debug Bar](https://github.com/thiagomrvieira/saci/blob/main/src/assets/images/saci-default.png)
Dark theme:
![Saci Debug Bar](https://github.com/thiagomrvieira/saci/blob/main/src/assets/images/saci-dark.png)
Minimal theme:
![Saci Debug Bar](https://github.com/thiagomrvieira/saci/blob/main/src/assets/images/saci-minimal.png)

*Saci debug bar showing view loading times and data types*

## Versioning

This project follows SemVer (MAJOR.MINOR.PATCH):
- BREAKING changes → MAJOR (e.g. 2.0.0)
- Backward-compatible features → MINOR (e.g. 1.1.0)
- Backward-compatible bug fixes → PATCH (e.g. 1.0.1)

Recent changes include UI improvements, external CSS, variable value previews and state persistence. They are backward-compatible and qualify as a MINOR release bump (e.g., from 1.0.0 to 1.1.0).

## Development

To contribute to the project:

1. Clone the repository
2. Install dependencies: `composer install`
3. Make your changes
4. Test in a Laravel project

## License

MIT