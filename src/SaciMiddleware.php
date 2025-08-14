<?php

namespace ThiagoVieira\Saci;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ThiagoVieira\Saci\SaciConfig;
use ThiagoVieira\Saci\TemplateTracker;
use ThiagoVieira\Saci\DebugBarInjector;
use ThiagoVieira\Saci\RequestValidator;

class SaciMiddleware
{
    /**
     * Template tracker instance.
     */
    protected TemplateTracker $tracker;

    /**
     * Debug bar injector instance.
     */
    protected DebugBarInjector $injector;

    /**
     * Request validator instance.
     */
    protected RequestValidator $validator;

    /**
     * Create a new middleware instance.
     */
    public function __construct(
        TemplateTracker $tracker,
        DebugBarInjector $injector,
        RequestValidator $validator
    ) {
        $this->tracker = $tracker;
        $this->injector = $injector;
        $this->validator = $validator;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (!$this->validator->shouldTrace($request)) {
            return $next($request);
        }

        $this->registerViewTracker();

        $response = $next($request);

        return $this->injector->inject($response);
    }

    /**
     * Register the view tracker.
     */
    protected function registerViewTracker(): void
    {
        $this->tracker->register();
    }


}