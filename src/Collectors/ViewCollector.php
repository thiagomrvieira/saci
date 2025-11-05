<?php

namespace ThiagoVieira\Saci\Collectors;

use ThiagoVieira\Saci\TemplateTracker;

/**
 * Collects data about loaded Blade views and templates.
 *
 * Delegates to TemplateTracker which handles the actual tracking logic.
 */
class ViewCollector extends BaseCollector
{
    public function __construct(
        protected TemplateTracker $tracker
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'views';
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return 'Views';
    }

    /**
     * {@inheritdoc}
     */
    protected function doStart(): void
    {
        // Reset tracker for new request
        if (method_exists($this->tracker, 'resetForRequest')) {
            $this->tracker->resetForRequest();
        }

        // Register view tracking
        $this->tracker->register();
    }

    /**
     * {@inheritdoc}
     */
    protected function doCollect(): void
    {
        $this->data = [
            'templates' => $this->tracker->getTemplates(),
            'total' => $this->tracker->getTotal(),
            'request_id' => method_exists($this->tracker, 'getRequestId')
                ? $this->tracker->getRequestId()
                : null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doReset(): void
    {
        $this->tracker->clear();
    }

    /**
     * Get the request ID for this collection.
     */
    public function getRequestId(): ?string
    {
        return $this->data['request_id'] ?? null;
    }

    /**
     * Get templates array for backward compatibility.
     */
    public function getTemplates(): array
    {
        return $this->data['templates'] ?? [];
    }

    /**
     * Get total count for backward compatibility.
     */
    public function getTotal(): int
    {
        return $this->data['total'] ?? 0;
    }
}


