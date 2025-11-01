<?php

namespace ThiagoVieira\Saci;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\LogCollector;
use ThiagoVieira\Saci\Support\LogProcessor;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\Support\FilePathResolver;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Collects per-request metadata for the Resources tab.
 *
 * Responsibilities:
 * - Track basic route/controller info after the route is resolved
 * - Track application services resolved from the container during the request
 * - Expose a compact, render-ready structure via getData()
 */
class RequestResources
{
    /** @var array<string,mixed> */
    protected array $routeInfo = [];

    /** @var array<string,mixed> */
    protected array $requestMeta = [];

    /** @var array<string,mixed> */
    protected array $responseMeta = [];

    /** @var array<string,mixed> */
    protected array $authMeta = [];

    protected ?float $startTime = null;

    /** @var array<int,array<string,mixed>> */
    protected array $logsProcessed = [];

    public function __construct(
        protected DumpManager $dumpManager,
        protected TemplateTracker $tracker,
        protected LogCollector $logCollector,
        protected LogProcessor $logProcessor,
        protected LateLogsPersistence $lateLogsPersistence,
        protected FilePathResolver $pathResolver,
    ) {}

    /**
     * Prepare for a new request.
     */
    public function start(): void
    {
        $this->routeInfo = [];
        $this->requestMeta = [];
        $this->responseMeta = [];
        $this->authMeta = [];
        $this->startTime = microtime(true);
        $this->logsProcessed = [];

        // Start log collection
        $this->logCollector->start();
    }

    /**
     * Collect route/controller info from the request.
     */
    public function collectFromRequest(Request $request): void
    {
        $route = $request->route();
        if (!$route) {
            return;
        }

        $actionName = method_exists($route, 'getActionName') ? $route->getActionName() : null;
        $controller = null;
        $method = null;
        $controllerFile = null;

        if ($actionName && $actionName !== 'Closure') {
            if (strpos($actionName, '@') !== false) {
                [$controller, $method] = explode('@', $actionName, 2);
            } else {
                $controller = $actionName;
            }
            if ($controller && class_exists($controller)) {
                try {
                    if ($method) {
                        $ref = new \ReflectionMethod($controller, $method);
                        $controllerFile = $this->toRelativePath((string) $ref->getFileName());
                    } else {
                        $ref = new \ReflectionClass($controller);
                        $controllerFile = $this->toRelativePath((string) $ref->getFileName());
                    }
                } catch (\Throwable $e) {
                    $controllerFile = null;
                }
            }
        } elseif ($actionName === 'Closure') {
            try {
                $closure = $route->getAction()['uses'] ?? null;
                if ($closure instanceof \Closure) {
                    $ref = new \ReflectionFunction($closure);
                    $controllerFile = $this->toRelativePath((string) $ref->getFileName());
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $domain = method_exists($route, 'domain') ? $route->domain() : null;
        $prefix = method_exists($route, 'getPrefix') ? $route->getPrefix() : null;
        $parameters = method_exists($route, 'parameters') ? (array) $route->parameters() : [];
        $action = method_exists($route, 'getAction') ? (array) $route->getAction() : [];
        $compiled = method_exists($route, 'getCompiled') ? $route->getCompiled() : null;
        $where = $action['where'] ?? [];

        $this->routeInfo = [
            'name' => method_exists($route, 'getName') ? $route->getName() : null,
            'uri' => method_exists($route, 'uri') ? $route->uri() : null,
            'methods' => method_exists($route, 'methods') ? $route->methods() : null,
            'domain' => $domain,
            'prefix' => $prefix,
            'parameters' => $parameters,
            'where' => $where,
            'compiled' => $compiled,
            'middleware' => method_exists($route, 'gatherMiddleware') ? $route->gatherMiddleware() : [],
            'action' => $actionName,
            'controller' => $controller,
            'controller_method' => $method,
            'controller_file' => $controllerFile,
            'controller_traits' => $controller && class_exists($controller) ? array_values(class_uses($controller)) : [],
        ];

        // Build route-related dump previews
        $this->buildRouteDumps();

        // Basic request meta
        $this->requestMeta = [
            'method' => $request->getMethod(),
            'full_url' => $request->fullUrl(),
            'format' => $request->getRequestFormat(),
            'headers' => [
                'accept' => $request->header('Accept'),
                'accept-language' => $request->header('Accept-Language'),
                'user-agent' => $request->header('User-Agent'),
                'content-type' => $request->header('Content-Type'),
            ],
            'cookies' => $request->cookies->all(),
            'query' => $request->query(),
            'request' => $request->request->all(),
            'headers_all' => $request->headers->all(),
            'session' => $request->hasSession() ? (array) $request->session()->all() : [],
        ];

        // Raw body (safe, limited size, omit multipart)
        try {
            $contentType = (string) ($request->header('Content-Type') ?? '');
            if (stripos($contentType, 'multipart/form-data') !== false) {
                $this->requestMeta['raw'] = '[multipart/form-data omitted]';
            } else {
                $raw = (string) $request->getContent();
                $raw = mb_substr($raw, 0, 8000);
                // Pretty print JSON if applicable
                $json = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $raw = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                }
                $this->requestMeta['raw'] = $raw;
            }
        } catch (\Throwable $e) {
            $this->requestMeta['raw'] = null;
        }

        // Build previews and lazy dumps for headers/body
        try {
            $reqId = method_exists($this->tracker, 'getRequestId') ? $this->tracker->getRequestId() : null;
            if ($reqId) {
                // Headers
                $headersAll = $this->requestMeta['headers_all'] ?? [];
                $this->requestMeta['headers_preview'] = $this->dumpManager->buildPreview($headersAll);
                $this->requestMeta['headers_dump_id'] = $this->dumpManager->storeDump($reqId, $headersAll);
                $this->requestMeta['headers_inline_html'] = $this->buildInlineHtmlIfSmall($headersAll);

                // Raw body
                $rawBody = $this->requestMeta['raw'] ?? '';
                $this->requestMeta['raw_preview'] = $this->dumpManager->buildPreview($rawBody);
                $this->requestMeta['raw_dump_id'] = $this->dumpManager->storeDump($reqId, $rawBody);
                $this->requestMeta['raw_inline_html'] = $this->buildInlineHtmlIfSmall($rawBody);

                // Query string params
                $query = $this->requestMeta['query'] ?? [];
                $this->requestMeta['query_preview'] = $this->dumpManager->buildPreview($query);
                $this->requestMeta['query_dump_id'] = $this->dumpManager->storeDump($reqId, $query);
                $this->requestMeta['query_inline_html'] = $this->buildInlineHtmlIfSmall($query);

                // Cookies
                $cookies = $this->requestMeta['cookies'] ?? [];
                $this->requestMeta['cookies_preview'] = $this->dumpManager->buildPreview($cookies);
                $this->requestMeta['cookies_dump_id'] = $this->dumpManager->storeDump($reqId, $cookies);
                $this->requestMeta['cookies_inline_html'] = $this->buildInlineHtmlIfSmall($cookies);

                // Session
                $session = $this->requestMeta['session'] ?? [];
                $this->requestMeta['session_preview'] = $this->dumpManager->buildPreview($session);
                $this->requestMeta['session_dump_id'] = $this->dumpManager->storeDump($reqId, $session);
                $this->requestMeta['session_inline_html'] = $this->buildInlineHtmlIfSmall($session);
            }
        } catch (\Throwable $e) {
            // ignore dump failures gracefully
        }

        // Auth meta
        try {
            $defaultGuard = config('auth.defaults.guard');
            $user = Auth::guard($defaultGuard)->user();
            $this->authMeta = [
                'guard' => $defaultGuard,
                'authenticated' => $user ? true : false,
                'id' => $user && method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null,
                'email' => $user && isset($user->email) ? $user->email : null,
                'name' => $user && isset($user->name) ? $user->name : null,
            ];
        } catch (\Throwable $e) {
            $this->authMeta = [ 'guard' => null, 'authenticated' => false ];
        }
    }

    protected function buildRouteDumps(): void
    {
        try {
            $reqId = method_exists($this->tracker, 'getRequestId') ? $this->tracker->getRequestId() : null;
            if (!$reqId) return;

            // Middleware stack
            $mw = $this->routeInfo['middleware'] ?? [];
            $this->routeInfo['middleware_preview'] = $this->dumpManager->buildPreview($mw);
            $this->routeInfo['middleware_dump_id'] = $this->dumpManager->storeDump($reqId, $mw);
            $this->routeInfo['middleware_inline_html'] = $this->buildInlineHtmlIfSmall($mw);

            // Parameters
            $params = $this->routeInfo['parameters'] ?? [];
            $this->routeInfo['parameters_preview'] = $this->dumpManager->buildPreview($params);
            $this->routeInfo['parameters_dump_id'] = $this->dumpManager->storeDump($reqId, $params);
            $this->routeInfo['parameters_inline_html'] = $this->buildInlineHtmlIfSmall($params);

            // Where constraints
            $where = $this->routeInfo['where'] ?? [];
            $this->routeInfo['where_preview'] = $this->dumpManager->buildPreview($where);
            $this->routeInfo['where_dump_id'] = $this->dumpManager->storeDump($reqId, $where);
            $this->routeInfo['where_inline_html'] = $this->buildInlineHtmlIfSmall($where);

            // Compiled route
            $compiled = $this->routeInfo['compiled'] ?? null;
            $this->routeInfo['compiled_preview'] = $this->dumpManager->buildPreview($compiled);
            $this->routeInfo['compiled_dump_id'] = $this->dumpManager->storeDump($reqId, $compiled);
            $this->routeInfo['compiled_inline_html'] = $this->buildInlineHtmlIfSmall($compiled);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Collect response info and compute duration.
     */
    public function collectFromResponse(SymfonyResponse $response): void
    {
        $durationMs = null;
        if ($this->startTime !== null) {
            $durationMs = round((microtime(true) - $this->startTime) * 1000, 2);
        }
        $this->responseMeta = [
            'status' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            'headers' => $response->headers->all(),
            'duration_ms' => $durationMs,
        ];

        // Process logs collected so far (before response is sent)
        $this->processInitialLogs();
    }

    /**
     * Process logs collected before response was sent.
     */
    protected function processInitialLogs(): void
    {
        $requestId = $this->getRequestId();
        $rawLogs = $this->logCollector->getRawLogs();

        $this->logsProcessed = $this->logProcessor->process($rawLogs, $requestId);
    }

    /**
     * Process and persist late logs (after response sent).
     * Called from middleware terminate().
     */
    public function processLateLogsIfNeeded(): void
    {
        $processedCount = count($this->logsProcessed);
        $rawLogs = $this->logCollector->getRawLogs();

        // Check if new logs were collected
        if (count($rawLogs) <= $processedCount) {
            return;
        }

        // Process only the new logs
        $requestId = $this->getRequestId();
        $newLogs = $this->logProcessor->process($rawLogs, $requestId, $processedCount);

        // Add to processed logs
        $this->logsProcessed = array_merge($this->logsProcessed, $newLogs);

        // Persist late logs for AJAX retrieval
        if ($requestId && !empty($newLogs)) {
            $this->lateLogsPersistence->persist($requestId, $newLogs);
        }
    }

    /**
     * Get current request ID.
     */
    protected function getRequestId(): ?string
    {
        return method_exists($this->tracker, 'getRequestId')
            ? $this->tracker->getRequestId()
            : null;
    }

    /**
     * Build a small, fully inlined HTML dump for fast UI when values are tiny.
     */
    protected function buildInlineHtmlIfSmall(mixed $value): ?string
    {
        try {
            // Fast path for scalars and small arrays/objects based on preview length
            $preview = $this->dumpManager->buildPreview($value);
            // Threshold heurística: até 120 chars de preview é aceitável inline
            if (mb_strlen((string) $preview) <= 120) {
                $data = $this->dumpManager->clonePreview($value);
                // Reutiliza o HTML dumper (cores/coerência) mas sem cabeçalhos inline
                return $this->dumpManager->renderHtml($data);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * Get all collected data for rendering.
     */
    public function getData(): array
    {
        return [
            'route' => $this->routeInfo,
            'request' => $this->requestMeta,
            'response' => $this->responseMeta,
            'auth' => $this->authMeta,
            'logs' => $this->logsProcessed,
        ];
    }

    /**
     * Convert an absolute path into a project-relative path.
     * Delegates to FilePathResolver for consistency.
     */
    protected function toRelativePath(string $absolutePath): string
    {
        return $this->pathResolver->toRelative($absolutePath);
    }
}


