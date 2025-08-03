# Saci

A modern, elegant Laravel debugger that shows loaded views and their data in a floating bar.

## Installation

1. Install the package via Composer:
```bash
composer require thiago-vieira/saci
```

2. Publish the configuration file (optional):
```bash
php artisan vendor:publish --tag=saci-config
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
        'theme' => 'dark',
        'max_height' => '30vh'
    ]
];
```

## Usage

After installation, Saci will be automatically activated in development environments. It will show a floating bar at the bottom of the page with information about loaded views.

### Features

- Shows all loaded Blade views
- Displays data types passed to each view
- Responsive and collapsible interface
- Configurable for different environments
- Sensitive data protection

### Environment Variables

```env
SACI_ENABLED=true
```

## Development

To contribute to the project:

1. Clone the repository
2. Install dependencies: `composer install`
3. Make your changes
4. Test in a Laravel project

## License

MIT