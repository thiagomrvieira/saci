<?php

namespace ThiagoVieira\Saci\Collectors;

use ThiagoVieira\Saci\Support\LogCollector as SupportLogCollector;
use ThiagoVieira\Saci\Support\LogProcessor;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\TemplateTracker;

/**
 * Collects application logs during the request lifecycle.
 *
 * Responsibilities:
 * - Start log collection
 * - Process collected logs
 * - Handle late logs (after response sent)
 */
class LogCollector extends BaseCollector
{
    protected array $logsProcessed = [];

    public function __construct(
        protected SupportLogCollector $logCollector,
        protected LogProcessor $logProcessor,
        protected LateLogsPersistence $lateLogsPersistence,
        protected TemplateTracker $tracker
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'logs';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return 'Logs';
    }

    /**
     * {@inheritdoc}
     */
    protected function doStart(): void
    {
        $this->logsProcessed = [];
        $this->logCollector->start();
    }

    /**
     * {@inheritdoc}
     */
    protected function doCollect(): void
    {
        $requestId = $this->getRequestId();
        $rawLogs = $this->logCollector->getRawLogs();

        $this->logsProcessed = $this->logProcessor->process($rawLogs, $requestId);

        $this->data = [
            'logs' => $this->logsProcessed,
        ];
    }

    /**
     * Process and persist late logs (after response sent).
     * Called from middleware terminate().
     */
    public function processLateLogs(): void
    {
        if (!$this->isCollecting) {
            return;
        }

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
        $this->data['logs'] = $this->logsProcessed;

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
     * {@inheritdoc}
     */
    protected function doReset(): void
    {
        $this->logsProcessed = [];
    }

    /**
     * Get logs array for backward compatibility.
     */
    public function getLogs(): array
    {
        return $this->data['logs'] ?? [];
    }
}


