<?php

namespace ThiagoVieira\Saci;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\Collectors\RequestCollector;
use ThiagoVieira\Saci\Collectors\RouteCollector;
use ThiagoVieira\Saci\Collectors\AuthCollector;
use ThiagoVieira\Saci\Collectors\LogCollector;

/**
 * Backward compatibility adapter for RequestResources.
 *
 * This class maintains the old RequestResources interface while
 * delegating to the new collector architecture.
 *
 * @deprecated Use CollectorRegistry and individual collectors instead
 */
class RequestResourcesAdapter
{
    public function __construct(
        protected CollectorRegistry $registry
    ) {}

    /**
     * Prepare for a new request.
     */
    public function start(): void
    {
        $this->registry->startAll();
    }

    /**
     * Collect route/controller info from the request.
     */
    public function collectFromRequest(Request $request): void
    {
        // Set request on collectors
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
     * Collect response info and compute duration.
     */
    public function collectFromResponse(SymfonyResponse $response): void
    {
        if ($collector = $this->registry->get('request')) {
            if ($collector instanceof RequestCollector) {
                $collector->setResponse($response);
            }
        }

        // Trigger collection on all collectors
        $this->registry->collectAll();
    }

    /**
     * Process and persist late logs (after response sent).
     */
    public function processLateLogsIfNeeded(): void
    {
        $logCollector = $this->registry->get('logs');
        if ($logCollector instanceof LogCollector) {
            $logCollector->processLateLogs();
        }
    }

    /**
     * Get all collected data for rendering.
     */
    public function getData(): array
    {
        $requestCollector = $this->registry->get('request');
        $routeCollector = $this->registry->get('route');
        $authCollector = $this->registry->get('auth');
        $logCollector = $this->registry->get('logs');

        $requestData = $requestCollector instanceof RequestCollector ? $requestCollector->getData() : [];
        $routeData = $routeCollector instanceof RouteCollector ? $routeCollector->getData() : [];
        $authData = $authCollector instanceof AuthCollector ? $authCollector->getData() : [];
        $logData = $logCollector instanceof LogCollector ? $logCollector->getData() : [];

        return [
            'route' => $routeData,
            'request' => $requestData['request'] ?? [],
            'response' => $requestData['response'] ?? [],
            'auth' => $authData,
            'logs' => $logData['logs'] ?? [],
        ];
    }
}


