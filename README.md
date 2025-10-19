# Saci

Zero‑config, modern Laravel debug bar for Blade views, requests and routes.

## Install

```bash
composer require thiago-vieira/saci
```

Works out of the box. Set `SACI_ENABLED=true` to show the bar.
Publish assets once (recommended for CSP):

Optional (once):
```bash
php artisan vendor:publish --tag=saci-config
php artisan vendor:publish --tag=saci-assets
```

## What you get

- Views tab: loaded views, variables with on‑demand dumps (Symfony VarDumper), total loading time
- Request tab: status, response time, method/URI, headers, body, query, cookies, session (safe/limited)
- Route tab: name, uri, methods, domain, prefix, action/controller, parameters, middleware, where (on‑demand dumps)
- Persistent UI state (collapsed, height, per-card/per-variable; survives refresh)
- Resizable and accessible UI
 - CSP‑friendly: external CSS/JS with inline fallbacks and optional nonce

Env (optional):
```env
SACI_ENABLED=true
SACI_THEME=default   # default|dark|minimal
SACI_TRANSPARENCY=0.85
SACI_TRACK_PERFORMANCE=true
SACI_ALLOW_AJAX=false
SACI_ALLOW_IPS=127.0.0.1,::1
SACI_PREVIEW_MAX_CHARS=70
SACI_DUMP_MAX_ITEMS=10000
SACI_DUMP_MAX_STRING=10000
```

## License

MIT