<?php

namespace ThiagoVieira\Saci\Collectors\Contracts;

/**
 * Interface for all data collectors.
 *
 * Each collector is responsible for gathering a specific type of data
 * during the request lifecycle (views, queries, HTTP calls, etc).
 */
interface CollectorInterface
{
    /**
     * Get the collector's unique name (used for tabs, config, etc).
     */
    public function getName(): string;

    /**
     * Get the display label for the collector.
     */
    public function getLabel(): string;

    /**
     * Start collecting data for this request.
     */
    public function start(): void;

    /**
     * Collect data from the current request.
     * Called after the application has processed the request.
     */
    public function collect(): void;

    /**
     * Get the collected data in a structured format.
     *
     * @return array
     */
    public function getData(): array;

    /**
     * Check if this collector is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Reset the collector's state for a new request.
     */
    public function reset(): void;
}


