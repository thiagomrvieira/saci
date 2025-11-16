<?php

namespace ThiagoVieira\Saci;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\Log;
use ThiagoVieira\Saci\SaciInfo;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\Collectors\ViewCollector;
use ThiagoVieira\Saci\Collectors\RequestCollector;
use ThiagoVieira\Saci\Collectors\RouteCollector;
use ThiagoVieira\Saci\Collectors\AuthCollector;
use ThiagoVieira\Saci\Collectors\LogCollector;
use ThiagoVieira\Saci\Collectors\DatabaseCollector;

class DebugBarInjector
{
    /**
     * Create a new debug bar injector instance.
     */
    public function __construct(
        protected CollectorRegistry $registry
    ) {}

    /**
     * Inject the debug bar into the response.
     */
    public function inject(SymfonyResponse $response): SymfonyResponse
    {
        $contentType = $response->headers->get('Content-Type', '');
        $disposition = $response->headers->get('Content-Disposition', '');
        if (stripos($contentType, 'text/html') === false) {
            return $response;
        }
        if (stripos($disposition, 'attachment') !== false) {
            return $response;
        }

        $content = $response->getContent();

        if (empty($content)) {
            return $response;
        }

        // Avoid duplicate injection if the bar already exists
        if (str_contains($content, 'id="saci"') || str_contains($content, '<div id="saci"')) {
            return $response;
        }

        $debugContent = $this->renderDebugBar();

        if (!str_contains($content, '</body>')) {
            $content .= $debugContent;
        } else {
            $content = str_replace('</body>', $debugContent . '</body>', $content);
        }

        $response->setContent($content);

        return $response;
    }

    /**
     * Render the debug bar view.
     */
    protected function renderDebugBar(): string
    {
        try {
            $viewData = $this->prepareViewData();
            return view('saci::bar', $viewData)->render();
        } catch (\Exception $e) {
            Log::error('Saci view error: ' . $e->getMessage());

            return '<!-- Saci Error: View not loaded -->';
        }
    }

    /**
     * Prepare view data from collectors.
     *
     * Formats data to maintain backward compatibility with existing views.
     */
    protected function prepareViewData(): array
    {
        $viewCollector = $this->registry->get('views');
        $requestCollector = $this->registry->get('request');
        $routeCollector = $this->registry->get('route');
        $authCollector = $this->registry->get('auth');
        $logCollector = $this->registry->get('logs');
        $databaseCollector = $this->registry->get('database');

        // Extract data from collectors
        $viewData = $viewCollector instanceof ViewCollector ? $viewCollector->getData() : [];
        $requestData = $requestCollector instanceof RequestCollector ? $requestCollector->getData() : [];
        $routeData = $routeCollector instanceof RouteCollector ? $routeCollector->getData() : [];
        $authData = $authCollector instanceof AuthCollector ? $authCollector->getData() : [];
        $logData = $logCollector instanceof LogCollector ? $logCollector->getData() : [];
        $databaseData = $databaseCollector instanceof DatabaseCollector ? $databaseCollector->getData() : [];

        // Combine into resources structure (backward compatible)
        $resources = [
            'request' => $requestData['request'] ?? [],
            'response' => $requestData['response'] ?? [],
            'route' => $routeData,
            'auth' => $authData,
            'logs' => $logData['logs'] ?? [],
            'database' => $databaseData,
        ];

        return [
            'requestId' => $viewData['request_id'] ?? null,
            'templates' => $viewData['templates'] ?? [],
            'total' => $viewData['total'] ?? 0,
            'version' => SaciInfo::getVersion(),
            'author' => SaciInfo::getAuthor(),
            'resources' => $resources,
        ];
    }
}