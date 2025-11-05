<?php

namespace ThiagoVieira\Saci\Support;

use ThiagoVieira\Saci\Collectors\Contracts\CollectorInterface;
use Illuminate\Support\Collection;

/**
 * Registry for managing all data collectors.
 *
 * Responsibilities:
 * - Register collectors
 * - Start/stop all collectors
 * - Aggregate data from all collectors
 */
class CollectorRegistry
{
    /**
     * Registered collectors.
     *
     * @var Collection<string, CollectorInterface>
     */
    protected Collection $collectors;

    public function __construct()
    {
        $this->collectors = collect();
    }

    /**
     * Register a collector instance.
     */
    public function register(CollectorInterface $collector): self
    {
        $this->collectors->put($collector->getName(), $collector);
        return $this;
    }

    /**
     * Get a collector by name.
     */
    public function get(string $name): ?CollectorInterface
    {
        return $this->collectors->get($name);
    }

    /**
     * Get all registered collectors.
     *
     * @return Collection<string, CollectorInterface>
     */
    public function all(): Collection
    {
        return $this->collectors;
    }

    /**
     * Get only enabled collectors.
     *
     * @return Collection<string, CollectorInterface>
     */
    public function enabled(): Collection
    {
        return $this->collectors->filter(fn($collector) => $collector->isEnabled());
    }

    /**
     * Start all enabled collectors.
     */
    public function startAll(): void
    {
        $this->enabled()->each(fn($collector) => $collector->start());
    }

    /**
     * Collect data from all enabled collectors.
     */
    public function collectAll(): void
    {
        $this->enabled()->each(fn($collector) => $collector->collect());
    }

    /**
     * Reset all collectors.
     */
    public function resetAll(): void
    {
        $this->collectors->each(fn($collector) => $collector->reset());
    }

    /**
     * Get all collected data indexed by collector name.
     *
     * @return array<string, array>
     */
    public function getAllData(): array
    {
        return $this->enabled()
            ->mapWithKeys(fn($collector) => [
                $collector->getName() => $collector->getData()
            ])
            ->toArray();
    }

    /**
     * Check if a collector is registered.
     */
    public function has(string $name): bool
    {
        return $this->collectors->has($name);
    }

    /**
     * Get count of registered collectors.
     */
    public function count(): int
    {
        return $this->collectors->count();
    }
}


