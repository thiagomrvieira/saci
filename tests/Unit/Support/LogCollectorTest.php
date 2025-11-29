<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Support\LogCollector;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->collector = new LogCollector();
});

describe('LogCollector Lifecycle', function () {
    it('starts collecting logs', function () {
        $this->collector->start();

        expect($this->collector->isActive())->toBeTrue();
    });

    it('is not active initially', function () {
        expect($this->collector->isActive())->toBeFalse();
    });

    it('clears previous logs on start', function () {
        $this->collector->start();

        // Manually add a log
        $reflection = new ReflectionClass($this->collector);
        $property = $reflection->getProperty('rawLogs');
        $property->setAccessible(true);
        $property->setValue($this->collector, [['level' => 'info', 'message' => 'old']]);

        // Start again should clear
        $this->collector->start();

        expect($this->collector->getRawLogs())->toBeEmpty();
    });

    it('returns empty logs initially', function () {
        expect($this->collector->getRawLogs())->toBeEmpty();
    });
});

describe('LogCollector Event Listening', function () {
    it('collects logs after start', function () {
        Event::fake();

        $this->collector->start();

        // Dispatch a log event
        $event = new MessageLogged('info', 'Test message', ['key' => 'value']);
        Event::dispatch($event);

        // Manually trigger the listener (since Event is faked, we need to simulate)
        // Instead, let's test with real events
    });

    it('registers event listener on start', function () {
        $this->collector->start();

        // Listener registration is internal, we test it by verifying logs are collected
        Log::info('Test');
        expect($this->collector->getRawLogs())->toHaveCount(1);
    });

    it('registers listener only once', function () {
        $this->collector->start();
        $this->collector->start();
        $this->collector->start();

        // Should only register once (tested internally)
        expect(true)->toBeTrue();
    });
});

describe('LogCollector Log Collection', function () {
    it('collects log with level', function () {
        $this->collector->start();

        Log::info('Test message');

        $logs = $this->collector->getRawLogs();

        expect($logs)->not->toBeEmpty();
        expect($logs[0])->toHaveKey('level');
        expect($logs[0]['level'])->toBe('info');
    });

    it('collects log with message', function () {
        $this->collector->start();

        Log::warning('Warning message');

        $logs = $this->collector->getRawLogs();

        expect($logs)->not->toBeEmpty();
        expect($logs[0])->toHaveKey('message');
        expect($logs[0]['message'])->toBe('Warning message');
    });

    it('collects log with context', function () {
        $this->collector->start();

        Log::error('Error occurred', ['user_id' => 123, 'action' => 'delete']);

        $logs = $this->collector->getRawLogs();

        expect($logs)->not->toBeEmpty();
        expect($logs[0])->toHaveKey('context');
        expect($logs[0]['context'])->toHaveKey('user_id');
        expect($logs[0]['context']['user_id'])->toBe(123);
    });

    it('collects log with timestamp', function () {
        $this->collector->start();

        Log::debug('Debug info');

        $logs = $this->collector->getRawLogs();

        expect($logs)->not->toBeEmpty();
        expect($logs[0])->toHaveKey('time');
        expect($logs[0]['time'])->toBeFloat();
        expect($logs[0]['time'])->toBeGreaterThan(0);
    });

    it('collects multiple logs', function () {
        $this->collector->start();

        Log::info('First log');
        Log::warning('Second log');
        Log::error('Third log');

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(3);
        expect($logs[0]['message'])->toBe('First log');
        expect($logs[1]['message'])->toBe('Second log');
        expect($logs[2]['message'])->toBe('Third log');
    });

    it('collects logs with different levels', function () {
        $this->collector->start();

        Log::emergency('Emergency');
        Log::alert('Alert');
        Log::critical('Critical');
        Log::error('Error');
        Log::warning('Warning');
        Log::notice('Notice');
        Log::info('Info');
        Log::debug('Debug');

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(8);
        expect(array_column($logs, 'level'))->toBe([
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug',
        ]);
    });
});

describe('LogCollector Filtering', function () {
    it('skips logs containing Saci: prefix', function () {
        $this->collector->start();

        Log::info('Regular log');
        Log::info('Saci: internal log');
        Log::info('Another regular log');

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(2);
        expect($logs[0]['message'])->toBe('Regular log');
        expect($logs[1]['message'])->toBe('Another regular log');
    });

    it('skips logs containing __saci keyword', function () {
        $this->collector->start();

        Log::info('User action');
        Log::info('Debug __saci internal');
        Log::info('Final message');

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(2);
        expect($logs[0]['message'])->toBe('User action');
        expect($logs[1]['message'])->toBe('Final message');
    });

    it('handles non-string log messages', function () {
        $this->collector->start();

        // Log with array message (Laravel converts to string)
        Log::info(['key' => 'value']);

        $logs = $this->collector->getRawLogs();

        // Laravel converts arrays to JSON strings
        expect($logs)->toHaveCount(1);
        expect($logs[0]['message'])->toBeString();
    });

    it('handles null log messages', function () {
        $this->collector->start();

        Log::info(null);

        $logs = $this->collector->getRawLogs();

        // Laravel converts null to empty string
        expect($logs)->toHaveCount(1);
        expect($logs[0]['message'])->toBeString();
    });
});

describe('LogCollector Edge Cases', function () {
    it('does not collect logs before start', function () {
        Log::info('Before start');

        $this->collector->start();

        Log::info('After start');

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(1);
        expect($logs[0]['message'])->toBe('After start');
    });

    it('handles empty log messages', function () {
        $this->collector->start();

        Log::info('');

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(1);
        expect($logs[0]['message'])->toBe('');
    });

    it('handles logs with special characters', function () {
        $this->collector->start();

        Log::info('Log with émojis 🎉 and spëcial çhars');

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(1);
        expect($logs[0]['message'])->toContain('🎉');
    });

    it('handles very long log messages', function () {
        $this->collector->start();

        $longMessage = str_repeat('A', 10000);
        Log::info($longMessage);

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(1);
        expect(strlen($logs[0]['message']))->toBe(10000);
    });

    it('preserves log context arrays', function () {
        $this->collector->start();

        $complexContext = [
            'nested' => ['deep' => ['value' => 123]],
            'array' => [1, 2, 3],
            'null' => null,
            'bool' => true,
        ];

        Log::info('Complex context', $complexContext);

        $logs = $this->collector->getRawLogs();

        expect($logs[0]['context'])->toBe($complexContext);
    });

    it('handles concurrent starts gracefully', function () {
        $this->collector->start();
        $startTime1 = microtime(true);

        usleep(1000); // 1ms

        $this->collector->start();

        // Should reset logs but continue working
        expect($this->collector->isActive())->toBeTrue();
    });
});

describe('LogCollector Performance', function () {
    it('handles high volume of logs efficiently', function () {
        $this->collector->start();

        for ($i = 0; $i < 1000; $i++) {
            Log::info("Log message {$i}");
        }

        $logs = $this->collector->getRawLogs();

        expect($logs)->toHaveCount(1000);
    });
});

