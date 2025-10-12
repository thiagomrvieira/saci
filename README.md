# Saci

Zero‑config, modern Laravel debug bar for Blade views and requests.

## Install

```bash
composer require thiago-vieira/saci
```

Works out of the box. Set `SACI_ENABLED=true` to show the bar.

Optional (once):
```bash
php artisan vendor:publish --tag=saci-config
php artisan vendor:publish --tag=saci-assets
```

## What you get

- Views tab: loaded views, variables, total views loading time
- Request tab: status, method/URI, request time, content-type, headers, body (safe/limited)
- Persistent UI state (collapsed, height, per-card/per-variable)
- Resizable and accessible UI

Env (optional):
```env
SACI_ENABLED=true
SACI_THEME=default   # default|dark|minimal
SACI_TRANSPARENCY=0.85
SACI_TRACK_PERFORMANCE=true
```

## License

MIT