<?php

namespace ThiagoVieira\Saci;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestValidator
{
    /**
     * Determine if the request should be traced.
     */
    public function shouldTrace(Request $request): bool
    {
        // Never trace Saci's own endpoints to avoid recursive injection
        $path = $request->path();
        if (str_starts_with($path, '__saci/')) return false;

        if (!$this->isEnabled()) return false;
        if (!$request->acceptsHtml()) return false;
        if ($request->ajax() && !SaciConfig::get('allow_ajax', false)) return false;
        // Skip JSON-accepting clients when ajax off
        if (!$request->ajax() && str_contains((string) $request->header('Accept'), 'application/json')) return false;
        if (!$this->isAllowedClient($request)) return false;
        return true;
    }

    /**
     * Determine if the dump HTML can be served (looser: allows ajax).
     */
    public function shouldServeDump(Request $request): bool
    {
        // Allow serving dumps even for AJAX requests, but still block outsiders
        $path = $request->path();
        if (!str_starts_with($path, '__saci/dump/')) return false;
        if (!$this->isEnabled()) return false;
        if (!$this->isAllowedClient($request)) return false;
        return true;
    }

    /**
     * Determine if static assets can be served.
     * Less strict path check than dumps; used for CSS/JS under /__saci/assets.
     */
    public function shouldServeAssets(Request $request): bool
    {
        if (!$this->isEnabled()) return false;
        if (!$this->isAllowedClient($request)) return false;
        return true;
    }

    protected function isEnabled(): bool
    {
        $enabled = SaciConfig::get('enabled');
        if ($enabled === null) {
            return (bool) config('app.debug');
        }
        return (bool) $enabled;
    }

    protected function isAllowedClient(Request $request): bool
    {
        $ips = (array) SaciConfig::get('allow_ips', []);
        if (empty($ips)) return true;
        $clientIp = $request->ip();
        return in_array($clientIp, $ips, true);
    }

    public function shouldSkipResponse(object $response): bool
    {
        if ($response instanceof BinaryFileResponse) return true;
        if ($response instanceof StreamedResponse) return true;
        return false;
    }
}