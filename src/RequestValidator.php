<?php

namespace ThiagoVieira\Saci;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class RequestValidator
{
    /**
     * Determine if the request should be traced.
     */
    public function shouldTrace(Request $request): bool
    {
        return SaciConfig::isEnabled() &&
               $request->acceptsHtml() &&
               !$request->ajax();
    }

    /**
     * Check if current environment is valid for tracing.
     */
    protected function isValidEnvironment(): bool
    {
        // Environment is no longer used to gate visibility; retained for BC if referenced elsewhere
        return true;
    }
}