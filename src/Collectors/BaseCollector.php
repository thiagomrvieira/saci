<?php

namespace ThiagoVieira\Saci\Collectors;

use ThiagoVieira\Saci\Collectors\Contracts\CollectorInterface;

/**
 * Base collector implementation with common functionality.
 *
 * All collectors should extend this class to inherit:
 * - Enable/disable logic
 * - Reset functionality
 * - Common helpers
 */
abstract class BaseCollector implements CollectorInterface
{
    /**
     * Whether this collector is currently collecting data.
     */
    protected bool $isCollecting = false;

    /**
     * Collected data storage.
     */
    protected array $data = [];

    /**
     * {@inheritdoc}
     */
    abstract public function getName(): string;

    /**
     * {@inheritdoc}
     */
    abstract public function getLabel(): string;

    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->isCollecting = true;
        $this->data = [];
        $this->doStart();
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): void
    {
        if (!$this->isCollecting) {
            return;
        }

        $this->doCollect();
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        $collectorName = $this->getName();
        $configKey = "saci.collectors.{$collectorName}";

        // Default to true if not explicitly configured
        return config($configKey, true);
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->isCollecting = false;
        $this->data = [];
        $this->doReset();
    }

    /**
     * Hook for subclasses to implement start logic.
     */
    protected function doStart(): void
    {
        // Override in subclasses if needed
    }

    /**
     * Hook for subclasses to implement collection logic.
     */
    protected function doCollect(): void
    {
        // Override in subclasses if needed
    }

    /**
     * Hook for subclasses to implement reset logic.
     */
    protected function doReset(): void
    {
        // Override in subclasses if needed
    }
}


