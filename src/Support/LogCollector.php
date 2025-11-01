<?php

namespace ThiagoVieira\Saci\Support;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

/**
 * Collects application logs during request lifecycle.
 *
 * Responsibilities:
 * - Register Laravel log event listener
 * - Collect raw log entries (level, message, context, time)
 * - Filter out internal Saci logs
 */
class LogCollector
{
    /** @var array<int,array{level:string,message:mixed,context:array,time:float}> */
    protected array $rawLogs = [];

    protected ?float $startTime = null;
    protected bool $listenerRegistered = false;

    /**
     * Start collecting logs for a new request.
     */
    public function start(): void
    {
        $this->registerListenerOnce();
        $this->startTime = microtime(true);
        $this->rawLogs = [];
    }

    /**
     * Check if currently collecting logs.
     */
    public function isActive(): bool
    {
        return $this->startTime !== null;
    }

    /**
     * Get all collected raw logs.
     * @return array<int,array{level:string,message:mixed,context:array,time:float,file:?string}>
     */
    public function getRawLogs(): array
    {
        return $this->rawLogs;
    }

    /**
     * Register log listener once per application lifecycle.
     */
    protected function registerListenerOnce(): void
    {
        if ($this->listenerRegistered) {
            return;
        }

        $this->listenerRegistered = true;

        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            if (!$this->isActive()) {
                return;
            }

            if ($this->shouldSkipLog($event)) {
                return;
            }

            // Collect raw log data (no file resolution for performance)
            $this->rawLogs[] = [
                'level' => (string) $event->level,
                'message' => $event->message,
                'context' => (array) $event->context,
                'time' => microtime(true),
            ];
        });
    }

    /**
     * Determine if log should be skipped (internal Saci logs).
     */
    protected function shouldSkipLog(MessageLogged $event): bool
    {
        $message = is_string($event->message) ? $event->message : '';

        return str_contains($message, 'Saci:')
            || str_contains($message, '__saci');
    }
}

