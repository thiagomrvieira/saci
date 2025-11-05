<?php

namespace ThiagoVieira\Saci\Collectors;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\TemplateTracker;

/**
 * Collects HTTP request and response metadata.
 *
 * Responsibilities:
 * - Basic request meta (method, URL, format)
 * - Headers, cookies, query, body
 * - Response status, headers, duration
 */
class RequestCollector extends BaseCollector
{
    protected ?Request $request = null;
    protected ?SymfonyResponse $response = null;
    protected ?float $startTime = null;

    public function __construct(
        protected DumpManager $dumpManager,
        protected TemplateTracker $tracker
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'request';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return 'Request';
    }

    /**
     * {@inheritdoc}
     */
    protected function doStart(): void
    {
        $this->startTime = microtime(true);
    }

    /**
     * Set the request for collection.
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Set the response for collection.
     */
    public function setResponse(SymfonyResponse $response): void
    {
        $this->response = $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function doCollect(): void
    {
        if (!$this->request) {
            return;
        }

        $requestMeta = $this->collectRequestMeta($this->request);
        $responseMeta = $this->response ? $this->collectResponseMeta($this->response) : [];

        $this->data = [
            'request' => $requestMeta,
            'response' => $responseMeta,
        ];
    }

    /**
     * Collect request metadata.
     */
    protected function collectRequestMeta(Request $request): array
    {
        $meta = [
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
                $meta['raw'] = '[multipart/form-data omitted]';
            } else {
                $raw = (string) $request->getContent();
                $raw = mb_substr($raw, 0, 8000);
                // Pretty print JSON if applicable
                $json = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $raw = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                }
                $meta['raw'] = $raw;
            }
        } catch (\Throwable $e) {
            $meta['raw'] = null;
        }

        // Build previews and lazy dumps
        $this->attachDumps($meta);

        return $meta;
    }

    /**
     * Collect response metadata.
     */
    protected function collectResponseMeta(SymfonyResponse $response): array
    {
        $durationMs = null;
        if ($this->startTime !== null) {
            $durationMs = round((microtime(true) - $this->startTime) * 1000, 2);
        }

        return [
            'status' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            'headers' => $response->headers->all(),
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Attach dump previews and IDs to metadata.
     */
    protected function attachDumps(array &$meta): void
    {
        try {
            $reqId = method_exists($this->tracker, 'getRequestId') ? $this->tracker->getRequestId() : null;
            if (!$reqId) {
                return;
            }

            // Headers
            $headersAll = $meta['headers_all'] ?? [];
            $meta['headers_preview'] = $this->dumpManager->buildPreview($headersAll);
            $meta['headers_dump_id'] = $this->dumpManager->storeDump($reqId, $headersAll);
            $meta['headers_inline_html'] = $this->buildInlineHtmlIfSmall($headersAll);

            // Raw body
            $rawBody = $meta['raw'] ?? '';
            $meta['raw_preview'] = $this->dumpManager->buildPreview($rawBody);
            $meta['raw_dump_id'] = $this->dumpManager->storeDump($reqId, $rawBody);
            $meta['raw_inline_html'] = $this->buildInlineHtmlIfSmall($rawBody);

            // Query string params
            $query = $meta['query'] ?? [];
            $meta['query_preview'] = $this->dumpManager->buildPreview($query);
            $meta['query_dump_id'] = $this->dumpManager->storeDump($reqId, $query);
            $meta['query_inline_html'] = $this->buildInlineHtmlIfSmall($query);

            // Cookies
            $cookies = $meta['cookies'] ?? [];
            $meta['cookies_preview'] = $this->dumpManager->buildPreview($cookies);
            $meta['cookies_dump_id'] = $this->dumpManager->storeDump($reqId, $cookies);
            $meta['cookies_inline_html'] = $this->buildInlineHtmlIfSmall($cookies);

            // Session
            $session = $meta['session'] ?? [];
            $meta['session_preview'] = $this->dumpManager->buildPreview($session);
            $meta['session_dump_id'] = $this->dumpManager->storeDump($reqId, $session);
            $meta['session_inline_html'] = $this->buildInlineHtmlIfSmall($session);
        } catch (\Throwable $e) {
            // Ignore dump failures gracefully
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
        $this->response = null;
        $this->startTime = null;
    }
}


