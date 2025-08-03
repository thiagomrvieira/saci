# Testing Guide

## Quick Test

### 1. Install the Package
```bash
composer require thiago-vieira/saci
```

### 2. Environment Configuration
Make sure your `.env` file has:
```env
APP_ENV=local
SACI_ENABLED=true
```

### 3. Create a Test Route
In `routes/web.php`:
```php
Route::get('/test-saci', function () {
    return view('test', [
        'message' => 'Hello World',
        'users' => ['John', 'Jane', 'Bob'],
        'settings' => ['theme' => 'dark', 'language' => 'en']
    ]);
});
```

### 4. Create a Test View
In `resources/views/test.blade.php`:
```html
<!DOCTYPE html>
<html>
<head>
    <title>Test Saci</title>
</head>
<body>
    <h1>{{ $message }}</h1>
    <ul>
        @foreach($users as $user)
            <li>{{ $user }}</li>
        @endforeach
    </ul>
    <p>Theme: {{ $settings['theme'] }}</p>
</body>
</html>
```

### 5. Test the Package
```bash
php artisan serve
```

Visit `http://localhost:8000/test-saci` and you should see the Saci bar at the bottom of the page.

### 6. Check Logs
If there are issues, check the logs in `storage/logs/laravel.log`. The logs now include detailed information about:
- Configuration loading
- Views loading
- Middleware registration
- Middleware execution
- View tracking

## Expected Logs

When working correctly, you should see logs like:
```
[2025-08-02 08:25:37] local.DEBUG: Saci config loaded from: /path/to/config
[2025-08-02 08:25:37] local.DEBUG: Saci views loaded from: /path/to/views
[2025-08-02 08:25:37] local.DEBUG: Saci middleware registered successfully
[2025-08-02 08:25:37] local.DEBUG: SaciMiddleware started
[2025-08-02 08:25:37] local.DEBUG: View tracked: resources/views/test.blade.php
```

## Troubleshooting

If you still have issues:

1. **Check logs**: `tail -f storage/logs/laravel.log`
2. **Clear cache**: `php artisan config:clear && php artisan view:clear`
3. **Check environment**: Make sure `APP_ENV=local`
4. **Check configuration**: Confirm `SACI_ENABLED=true`