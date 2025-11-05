<?php

namespace ThiagoVieira\Saci;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ThiagoVieira\Saci\SaciConfig;
use ThiagoVieira\Saci\DebugBarInjector;
use ThiagoVieira\Saci\RequestValidator;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\Collectors\ViewCollector;
use ThiagoVieira\Saci\Collectors\RequestCollector;
use ThiagoVieira\Saci\Collectors\RouteCollector;
use ThiagoVieira\Saci\Collectors\AuthCollector;
use ThiagoVieira\Saci\Collectors\LogCollector;

class SaciMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected CollectorRegistry $registry,
        protected DebugBarInjector $injector,
        protected RequestValidator $validator
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (!$this->validator->shouldTrace($request)) {
            return $next($request);
        }

        // Reset all collectors for new request
        $this->registry->resetAll();

        // Start all enabled collectors
        $this->registry->startAll();

        // Set request on collectors that need it
        $this->setRequestOnCollectors($request);

        $response = $next($request);

        if (method_exists($this->validator, 'shouldSkipResponse') && $this->validator->shouldSkipResponse($response)) {
            return $response;
        }

        // Set response on collectors that need it
        $this->setResponseOnCollectors($response);

        // Collect data from all collectors
        $this->registry->collectAll();

        return $this->injector->inject($response);
    }

    /**
     * Perform any final actions for the request lifecycle.
     * This runs AFTER the response has been sent to the browser,
     * allowing us to capture late logs (terminable middleware, jobs, etc.)
     */
    public function terminate(Request $request, SymfonyResponse $response): void
    {
        if (!$this->validator->shouldTrace($request)) {
            return;
        }

        if (method_exists($this->validator, 'shouldSkipResponse') && $this->validator->shouldSkipResponse($response)) {
            return;
        }

        // Process late logs if collector exists
        $logCollector = $this->registry->get('logs');
        if ($logCollector instanceof LogCollector) {
            $logCollector->processLateLogs();
        }
    }

    /**
     * Set request on collectors that need it.
     */
    protected function setRequestOnCollectors(Request $request): void
    {
        if ($collector = $this->registry->get('request')) {
            if ($collector instanceof RequestCollector) {
                $collector->setRequest($request);
            }
        }

        if ($collector = $this->registry->get('route')) {
            if ($collector instanceof RouteCollector) {
                $collector->setRequest($request);
            }
        }

        if ($collector = $this->registry->get('auth')) {
            if ($collector instanceof AuthCollector) {
                $collector->setRequest($request);
            }
        }
    }

    /**
     * Set response on collectors that need it.
     */
    protected function setResponseOnCollectors(SymfonyResponse $response): void
    {
        if ($collector = $this->registry->get('request')) {
            if ($collector instanceof RequestCollector) {
                $collector->setResponse($response);
            }
        }
    }
}