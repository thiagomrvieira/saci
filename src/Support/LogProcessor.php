<?php

namespace ThiagoVieira\Saci\Support;

/**
 * Processes raw log entries into renderable format.
 *
 * Responsibilities:
 * - Format log timestamps
 * - Generate previews and dumps for log data
 * - Handle empty contexts gracefully
 */
class LogProcessor
{
    public function __construct(
        protected DumpManager $dumpManager
    ) {}

    /**
     * Process raw log entries into renderable format.
     *
     * @param array<int,array{level:string,message:mixed,context:array,time:float}> $rawLogs
     * @param string|null $requestId
     * @param int $startIdx Start processing from this index
     * @return array<int,array<string,mixed>>
     */
    public function process(array $rawLogs, ?string $requestId, int $startIdx = 0): array
    {
        $processed = [];

        for ($idx = $startIdx; $idx < count($rawLogs); $idx++) {
            try {
                $processed[] = $this->processLogEntry($rawLogs[$idx], $requestId);
            } catch (\Throwable $e) {
                // Skip individual log failures gracefully
                continue;
            }
        }

        return $processed;
    }

    /**
     * Process a single log entry.
     */
    protected function processLogEntry(array $entry, ?string $requestId): array
    {
        $level = strtolower((string) ($entry['level'] ?? 'info'));
        $message = $entry['message'] ?? '';
        $context = (array) ($entry['context'] ?? []);
        $time = isset($entry['time']) ? (float) $entry['time'] : microtime(true);

        // Build previews once (reuse for inline HTML check)
        $messagePreview = $this->dumpManager->buildPreview($message);
        $contextPreview = $this->buildContextPreview($context);

        return [
            'level' => $level,
            'time' => $this->formatTime($time),
            'message_preview' => $messagePreview,
            'message_dump_id' => $requestId ? $this->dumpManager->storeDump($requestId, $message) : null,
            'message_inline_html' => $requestId ? $this->buildInlineHtml($message, $messagePreview) : null,
            'context_preview' => $contextPreview,
            'context_dump_id' => $this->buildContextDumpId($context, $requestId),
            'context_inline_html' => $this->buildContextInlineHtml($context, $requestId, $contextPreview),
        ];
    }

    /**
     * Format timestamp with milliseconds.
     */
    protected function formatTime(float $time): string
    {
        $seconds = (int) floor($time);
        $millis = (int) round(($time - $seconds) * 1000);

        return date('H:i:s', $seconds) . '.' . str_pad((string) $millis, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Build context preview (empty string if context is empty).
     */
    protected function buildContextPreview(array $context): string
    {
        return empty($context) ? '' : $this->dumpManager->buildPreview($context);
    }

    /**
     * Build context dump ID (null if context is empty).
     */
    protected function buildContextDumpId(array $context, ?string $requestId): ?string
    {
        if (empty($context) || !$requestId) {
            return null;
        }

        return $this->dumpManager->storeDump($requestId, $context);
    }

    /**
     * Build context inline HTML (null if context is empty).
     *
     * @param array $context
     * @param string|null $requestId
     * @param string|null $previewHint Pre-computed preview
     */
    protected function buildContextInlineHtml(array $context, ?string $requestId, ?string $previewHint = null): ?string
    {
        if (empty($context) || !$requestId) {
            return null;
        }

        return $this->buildInlineHtml($context, $previewHint);
    }

    /**
     * Build inline HTML for small values.
     *
     * @param mixed $value
     * @param string|null $previewHint Pre-computed preview to avoid double processing
     */
    protected function buildInlineHtml(mixed $value, ?string $previewHint = null): ?string
    {
        try {
            // Use provided preview or compute it
            $preview = $previewHint ?? $this->dumpManager->buildPreview($value);

            // Only inline small values (< 120 chars in preview)
            if (mb_strlen((string) $preview) <= 120) {
                $data = $this->dumpManager->clonePreview($value);
                return $this->dumpManager->renderHtml($data);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}

