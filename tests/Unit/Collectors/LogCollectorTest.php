<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Collectors\LogCollector;
use ThiagoVieira\Saci\Support\LogCollector as SupportLogCollector;
use ThiagoVieira\Saci\Support\LogProcessor;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\TemplateTracker;

beforeEach(function () {
    $this->supportLogCollector = Mockery::mock(SupportLogCollector::class);
    $this->logProcessor = Mockery::mock(LogProcessor::class);
    $this->lateLogsPersistence = Mockery::mock(LateLogsPersistence::class);
    $this->tracker = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();

    $this->collector = new LogCollector(
        $this->supportLogCollector,
        $this->logProcessor,
        $this->lateLogsPersistence,
        $this->tracker
    );
});

afterEach(function () {
    Mockery::close();
});

describe('LogCollector Identity', function () {
    it('returns correct name', function () {
        expect($this->collector->getName())->toBe('logs');
    });

    it('returns correct label', function () {
        expect($this->collector->getLabel())->toBe('Logs');
    });

    it('is enabled by default', function () {
        config()->set('saci.collectors.logs', true);
        expect($this->collector->isEnabled())->toBeTrue();
    });
});

describe('LogCollector Lifecycle', function () {
    it('starts support log collector on start', function () {
        $this->supportLogCollector->shouldReceive('start')->once();

        $this->collector->start();
    });

    it('collects logs on collect', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test log'],
        ];
        $processedLogs = [
            ['level' => 'info', 'message' => 'Test log', 'timestamp' => '2024-11-25 10:00:00'],
        ];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($rawLogs);
        $this->logProcessor->shouldReceive('process')->with($rawLogs, 'req-123')->andReturn($processedLogs);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKey('logs');
        expect($data['logs'])->toBe($processedLogs);
    });

    it('resets state on reset', function () {
        $this->collector->reset();

        expect($this->collector->getLogs())->toBeEmpty();
    });
});

describe('LogCollector Log Collection', function () {
    it('collects empty logs', function () {
        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn([]);
        $this->logProcessor->shouldReceive('process')->andReturn([]);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toBeEmpty();
    });

    it('collects single log entry', function () {
        $rawLogs = [['level' => 'debug', 'message' => 'Debug message']];
        $processedLogs = [['level' => 'debug', 'message' => 'Debug message', 'timestamp' => '2024-11-25 10:00:00']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($rawLogs);
        $this->logProcessor->shouldReceive('process')->andReturn($processedLogs);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toHaveCount(1);
    });

    it('collects multiple log entries', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Info log'],
            ['level' => 'warning', 'message' => 'Warning log'],
            ['level' => 'error', 'message' => 'Error log'],
        ];
        $processedLogs = [
            ['level' => 'info', 'message' => 'Info log', 'timestamp' => '10:00:00'],
            ['level' => 'warning', 'message' => 'Warning log', 'timestamp' => '10:00:01'],
            ['level' => 'error', 'message' => 'Error log', 'timestamp' => '10:00:02'],
        ];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($rawLogs);
        $this->logProcessor->shouldReceive('process')->andReturn($processedLogs);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toHaveCount(3);
    });

    it('passes request ID to processor', function () {
        $rawLogs = [['level' => 'info', 'message' => 'Test']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($rawLogs);
        $this->tracker->shouldReceive('getRequestId')->andReturn('custom-request-id');
        $this->logProcessor->shouldReceive('process')->with($rawLogs, 'custom-request-id')->andReturn([]);

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toBeArray();
    });

    it('handles tracker without getRequestId method', function () {
        $tracker = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();
        $collector = new LogCollector(
            $this->supportLogCollector,
            $this->logProcessor,
            $this->lateLogsPersistence,
            $tracker
        );

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn([]);
        $this->logProcessor->shouldReceive('process')->with([], '')->andReturn([]);

        $collector->start();
        $collector->collect();

        expect(true)->toBeTrue(); // No exception thrown
    });
});

describe('LogCollector Late Logs', function () {
    it('processes late logs when new logs available', function () {
        // Initial collection
        $initialLogs = [['level' => 'info', 'message' => 'Initial']];
        $processedInitial = [['level' => 'info', 'message' => 'Initial', 'timestamp' => '10:00:00']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn($initialLogs);
        $this->logProcessor->shouldReceive('process')->once()->with($initialLogs, 'req-123')->andReturn($processedInitial);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toHaveCount(1);

        // Late logs collection
        $allLogs = [
            ['level' => 'info', 'message' => 'Initial'],
            ['level' => 'debug', 'message' => 'Late log'],
        ];
        $newLogs = [['level' => 'debug', 'message' => 'Late log', 'timestamp' => '10:00:05']];

        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn($allLogs);
        $this->logProcessor->shouldReceive('process')->once()->with($allLogs, 'req-123', 1)->andReturn($newLogs);
        $this->lateLogsPersistence->shouldReceive('persist')->once()->with('req-123', $newLogs);

        $this->collector->processLateLogs();

        $logs = $this->collector->getLogs();
        expect($logs)->toHaveCount(2);
        expect($logs[0]['message'])->toBe('Initial');
        expect($logs[1]['message'])->toBe('Late log');
    });

    it('does nothing when no new logs', function () {
        // Initial collection
        $logs = [['level' => 'info', 'message' => 'Only log']];
        $processed = [['level' => 'info', 'message' => 'Only log', 'timestamp' => '10:00:00']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($logs);
        $this->logProcessor->shouldReceive('process')->with($logs, 'req-123')->andReturn($processed);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        // Try to process late logs but no new logs available
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($logs); // Same count
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->processLateLogs();

        expect($this->collector->getLogs())->toHaveCount(1);
    });

    it('does not process late logs when not collecting', function () {
        $this->collector->processLateLogs();

        // Should not throw and not persist anything
        expect(true)->toBeTrue();
    });

    it('persists late logs for AJAX retrieval', function () {
        $initialLogs = [['level' => 'info', 'message' => 'Initial']];
        $processedInitial = [['level' => 'info', 'message' => 'Initial']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn($initialLogs);
        $this->logProcessor->shouldReceive('process')->once()->with($initialLogs, 'req-123')->andReturn($processedInitial);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        // Late logs
        $allLogs = [
            ['level' => 'info', 'message' => 'Initial'],
            ['level' => 'error', 'message' => 'Late error'],
        ];
        $newLogs = [['level' => 'error', 'message' => 'Late error']];

        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn($allLogs);
        $this->logProcessor->shouldReceive('process')->once()->with($allLogs, 'req-123', 1)->andReturn($newLogs);
        $this->lateLogsPersistence->shouldReceive('persist')->once()->with('req-123', $newLogs);

        $this->collector->processLateLogs();

        expect($this->collector->getLogs())->toHaveCount(2);
    });
});

describe('LogCollector Backward Compatibility', function () {
    it('provides getLogs() method', function () {
        $processedLogs = [['level' => 'info', 'message' => 'Test log']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn([]);
        $this->logProcessor->shouldReceive('process')->andReturn($processedLogs);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toBe($processedLogs);
    });

    it('returns empty array when no data collected', function () {
        expect($this->collector->getLogs())->toBeEmpty();
    });
});

describe('LogCollector Integration', function () {
    it('follows complete lifecycle', function () {
        $rawLogs = [['level' => 'info', 'message' => 'Test']];
        $processedLogs = [['level' => 'info', 'message' => 'Test', 'timestamp' => '10:00:00']];

        // Start
        $this->supportLogCollector->shouldReceive('start')->once();
        $this->collector->start();

        // Collect
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($rawLogs);
        $this->logProcessor->shouldReceive('process')->andReturn($processedLogs);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->collector->collect();

        expect($this->collector->getLogs())->not->toBeEmpty();

        // Reset
        $this->collector->reset();
        expect($this->collector->getLogs())->toBeEmpty();
    });

    it('handles full lifecycle with late logs', function () {
        // Initial
        $initialLogs = [['level' => 'info', 'message' => 'Start']];
        $processed = [['level' => 'info', 'message' => 'Start']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn($initialLogs);
        $this->logProcessor->shouldReceive('process')->once()->with($initialLogs, 'req-123')->andReturn($processed);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toHaveCount(1);

        // Late logs
        $allLogs = [
            ['level' => 'info', 'message' => 'Start'],
            ['level' => 'info', 'message' => 'Late'],
        ];
        $lateLogs = [['level' => 'info', 'message' => 'Late']];

        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn($allLogs);
        $this->logProcessor->shouldReceive('process')->once()->with($allLogs, 'req-123', 1)->andReturn($lateLogs);
        $this->lateLogsPersistence->shouldReceive('persist')->once();

        $this->collector->processLateLogs();

        expect($this->collector->getLogs())->toHaveCount(2);

        // Reset
        $this->collector->reset();
        expect($this->collector->getLogs())->toBeEmpty();
    });
});

describe('LogCollector Edge Cases', function () {
    it('handles collection without start', function () {
        $this->collector->collect();

        expect($this->collector->getData())->toBeArray();
    });

    it('handles empty raw logs', function () {
        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn([]);
        $this->logProcessor->shouldReceive('process')->andReturn([]);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toBeEmpty();
    });

    it('handles processor returning empty array', function () {
        $rawLogs = [['level' => 'info', 'message' => 'Test']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->andReturn($rawLogs);
        $this->logProcessor->shouldReceive('process')->andReturn([]); // Processor filtered everything
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toBeEmpty();
    });

    it('handles late logs with empty new logs', function () {
        $logs = [['level' => 'info', 'message' => 'Test']];

        $this->supportLogCollector->shouldReceive('start');
        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn($logs);
        $this->logProcessor->shouldReceive('process')->once()->with($logs, 'req-123')->andReturn($logs);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getLogs())->toHaveCount(1);

        // More raw logs but processor returns empty
        $this->supportLogCollector->shouldReceive('getRawLogs')->once()->andReturn([...$logs, ['level' => 'debug']]);
        $this->logProcessor->shouldReceive('process')->once()->with([...$logs, ['level' => 'debug']], 'req-123', 1)->andReturn([]); // Empty new logs
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->lateLogsPersistence->shouldReceive('persist')->never();

        $this->collector->processLateLogs();

        // Should still have only 1 log
        expect($this->collector->getLogs())->toHaveCount(1);
    });
});

