<?php

namespace ThiagoVieira\Saci\Collectors;

use Illuminate\Http\Request;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\FilePathResolver;
use ThiagoVieira\Saci\TemplateTracker;

/**
 * Collects route and controller information.
 *
 * Responsibilities:
 * - Route metadata (name, URI, methods, parameters)
 * - Controller information (class, method, file)
 * - Middleware stack
 * - Route constraints (where clauses)
 */
class RouteCollector extends BaseCollector
{
    protected ?Request $request = null;

    public function __construct(
        protected DumpManager $dumpManager,
        protected FilePathResolver $pathResolver,
        protected TemplateTracker $tracker
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'route';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return 'Route';
    }

    /**
     * Set the request for collection.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * {@inheritdoc}
     */
    protected function doCollect(): void
    {
        if (!$this->request) {
            return;
        }

        $route = $this->request->route();
        if (!$route) {
            $this->data = [];
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
                        $controllerFile = $this->pathResolver->toRelative((string) $ref->getFileName());
                    } else {
                        $ref = new \ReflectionClass($controller);
                        $controllerFile = $this->pathResolver->toRelative((string) $ref->getFileName());
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
                    $controllerFile = $this->pathResolver->toRelative((string) $ref->getFileName());
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

        $this->data = [
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
        $this->attachDumps();
    }

    /**
     * Attach dump previews and IDs to route data.
     */
    protected function attachDumps(): void
    {
        try {
            $reqId = method_exists($this->tracker, 'getRequestId') ? $this->tracker->getRequestId() : null;
            if (!$reqId) {
                return;
            }

            // Middleware stack
            $mw = $this->data['middleware'] ?? [];
            $this->data['middleware_preview'] = $this->dumpManager->buildPreview($mw);
            $this->data['middleware_dump_id'] = $this->dumpManager->storeDump($reqId, $mw);
            $this->data['middleware_inline_html'] = $this->buildInlineHtmlIfSmall($mw);

            // Parameters
            $params = $this->data['parameters'] ?? [];
            $this->data['parameters_preview'] = $this->dumpManager->buildPreview($params);
            $this->data['parameters_dump_id'] = $this->dumpManager->storeDump($reqId, $params);
            $this->data['parameters_inline_html'] = $this->buildInlineHtmlIfSmall($params);

            // Where constraints
            $where = $this->data['where'] ?? [];
            $this->data['where_preview'] = $this->dumpManager->buildPreview($where);
            $this->data['where_dump_id'] = $this->dumpManager->storeDump($reqId, $where);
            $this->data['where_inline_html'] = $this->buildInlineHtmlIfSmall($where);

            // Compiled route
            $compiled = $this->data['compiled'] ?? null;
            $this->data['compiled_preview'] = $this->dumpManager->buildPreview($compiled);
            $this->data['compiled_dump_id'] = $this->dumpManager->storeDump($reqId, $compiled);
            $this->data['compiled_inline_html'] = $this->buildInlineHtmlIfSmall($compiled);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Build inline HTML for small values.
     */
    protected function buildInlineHtmlIfSmall(mixed $value): ?string
    {
        try {
            $preview = $this->dumpManager->buildPreview($value);
            if (mb_strlen((string) $preview) <= 120) {
                $data = $this->dumpManager->clonePreview($value);
                return $this->dumpManager->renderHtml($data);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function doReset(): void
    {
        $this->request = null;
    }
}


