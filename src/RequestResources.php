<?php

namespace ThiagoVieira\Saci;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /**
     * Prepare for a new request and register container listener (once).
     */
    public function start(): void
    {
        $this->routeInfo = [];
        $this->requestMeta = [];
        $this->responseMeta = [];
        $this->authMeta = [];
        $this->startTime = microtime(true);
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

        $this->routeInfo = [
            'name' => method_exists($route, 'getName') ? $route->getName() : null,
            'uri' => method_exists($route, 'uri') ? $route->uri() : null,
            'methods' => method_exists($route, 'methods') ? $route->methods() : null,
            'middleware' => method_exists($route, 'gatherMiddleware') ? $route->gatherMiddleware() : [],
            'action' => $actionName,
            'controller' => $controller,
            'controller_method' => $method,
            'controller_file' => $controllerFile,
            'controller_traits' => $controller && class_exists($controller) ? array_values(class_uses($controller)) : [],
        ];

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
        ];
    }

    /**
     * Convert an absolute path into a project-relative path prefixed with '/'.
     */
    protected function toRelativePath(string $absolutePath): string
    {
        $base = rtrim((string) base_path(), '/');
        $normalized = str_replace('\\', '/', $absolutePath);
        $rel = str_starts_with($normalized, $base . '/')
            ? substr($normalized, strlen($base))
            : $normalized;
        return $rel === '' ? $normalized : $rel;
    }
}


