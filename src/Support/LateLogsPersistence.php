<?php

namespace ThiagoVieira\Saci\Support;

/**
 * Handles persistence of late logs to storage.
 *
 * Responsibilities:
 * - Store late logs as JSON in dump storage
 * - Use special '__late_logs' identifier
 */
class LateLogsPersistence
{
    public function __construct(
        protected DumpStorage $storage
    ) {}

    /**
     * Persist late logs to storage for AJAX retrieval.
     *
     * @param string $requestId
     * @param array<int,array<string,mixed>> $lateLogs
     * @return bool Success status
     */
    public function persist(string $requestId, array $lateLogs): bool
    {
        if (empty($lateLogs)) {
            return false;
        }

        try {
            $json = json_encode([
                'logs' => $lateLogs,
                'count' => count($lateLogs)
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!$json) {
                return false;
            }

            $this->storage->storeHtml($requestId, '__late_logs', $json);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Retrieve late logs from storage.
     *
     * @param string $requestId
     * @return array{logs: array, count: int}
     */
    public function retrieve(string $requestId): array
    {
        try {
            $json = $this->storage->getHtml($requestId, '__late_logs');

            if ($json === null) {
                return ['logs' => [], 'count' => 0];
            }

            $data = json_decode($json, true);

            if (!is_array($data)) {
                return ['logs' => [], 'count' => 0];
            }

            return $data;
        } catch (\Throwable $e) {
            return ['logs' => [], 'count' => 0];
        }
    }
}

