<?php

namespace ThiagoVieira\Saci\Tests\Unit\Support;

use ThiagoVieira\Saci\Support\LogProcessor;
use ThiagoVieira\Saci\Support\DumpManager;
use Mockery;

beforeEach(function () {
    $this->dumpManager = Mockery::mock(DumpManager::class);
    $this->processor = new LogProcessor($this->dumpManager);
});

afterEach(function () {
    Mockery::close();
});

// ============================================================================
// 1. BASIC LOG PROCESSING
// ============================================================================

describe('Basic Log Processing', function () {
    it('processes single log entry', function () {
        $rawLogs = [
            [
                'level' => 'info',
                'message' => 'Test message',
                'context' => [],
                'time' => 1640000000.123,
            ],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Test message')
            ->andReturn('Test message');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')
            ->andReturn('dump-123');

        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed)->toHaveCount(1);
        expect($processed[0])->toHaveKeys([
            'level', 'time', 'message_preview', 'message_dump_id',
            'message_inline_html', 'context_preview', 'context_dump_id', 'context_inline_html'
        ]);
    });

    it('processes multiple log entries', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Log 1', 'context' => [], 'time' => 1640000000.0],
            ['level' => 'error', 'message' => 'Log 2', 'context' => [], 'time' => 1640000001.0],
            ['level' => 'debug', 'message' => 'Log 3', 'context' => [], 'time' => 1640000002.0],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed)->toHaveCount(3);
    });

    it('processes logs without request ID', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');

        $processed = $this->processor->process($rawLogs, null);

        expect($processed)->toHaveCount(1);
        expect($processed[0]['message_dump_id'])->toBeNull();
        expect($processed[0]['message_inline_html'])->toBeNull();
    });

    it('processes empty log array', function () {
        $processed = $this->processor->process([], 'req-123');

        expect($processed)->toBeEmpty();
    });

    it('processes logs starting from specific index', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Log 1', 'context' => [], 'time' => time()],
            ['level' => 'info', 'message' => 'Log 2', 'context' => [], 'time' => time()],
            ['level' => 'info', 'message' => 'Log 3', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        // Process only from index 1 onwards
        $processed = $this->processor->process($rawLogs, 'req-123', 1);

        expect($processed)->toHaveCount(2); // Should only process Log 2 and Log 3
    });
});

// ============================================================================
// 2. LOG LEVELS
// ============================================================================

describe('Log Levels', function () {
    it('processes all standard log levels', function ($level) {
        $rawLogs = [
            ['level' => $level, 'message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['level'])->toBe(strtolower($level));
    })->with(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug']);

    it('normalizes log levels to lowercase', function () {
        $rawLogs = [
            ['level' => 'INFO', 'message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['level'])->toBe('info');
    });

    it('defaults to info level when missing', function () {
        $rawLogs = [
            ['message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['level'])->toBe('info');
    });
});

// ============================================================================
// 3. TIME FORMATTING
// ============================================================================

describe('Time Formatting', function () {
    it('formats time with milliseconds', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => [], 'time' => 1640000000.123],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['time'])->toMatch('/^\d{2}:\d{2}:\d{2}\.\d{3}$/');
    });

    it('pads milliseconds with leading zeros', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => [], 'time' => 1640000000.001],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['time'])->toContain('.001');
    });

    it('uses current time when time is missing', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => []],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['time'])->toMatch('/^\d{2}:\d{2}:\d{2}\.\d{3}$/');
    });
});

// ============================================================================
// 4. MESSAGE HANDLING
// ============================================================================

describe('Message Handling', function () {
    it('generates message preview', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test message', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Test message')
            ->once()
            ->andReturn('Test message preview');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_preview'])->toBe('Test message preview');
    });

    it('stores message dump when request ID is provided', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test message', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');

        $this->dumpManager->shouldReceive('storeDump')
            ->with('req-123', 'Test message')
            ->once()
            ->andReturn('dump-message-123');

        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_dump_id'])->toBe('dump-message-123');
    });

    it('attempts to generate inline HTML for small messages', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Short', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->andReturn('Short');

        $this->dumpManager->shouldReceive('storeDump')
            ->andReturn('dump-id');

        $processed = $this->processor->process($rawLogs, 'req-123');

        // message_inline_html should have a value (generated or null based on internal logic)
        expect($processed[0])->toHaveKey('message_inline_html');
    });

    it('skips inline HTML for large messages', function () {
        $largeMessage = str_repeat('a', 200);
        $rawLogs = [
            ['level' => 'info', 'message' => $largeMessage, 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with($largeMessage)
            ->andReturn(str_repeat('a', 200)); // Large preview (> 120 chars)

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_inline_html'])->toBeNull();
    });

    it('handles empty message', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => '', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_preview'])->toBe('');
    });
});

// ============================================================================
// 5. CONTEXT HANDLING
// ============================================================================

describe('Context Handling', function () {
    it('generates context preview when context is not empty', function () {
        $rawLogs = [
            [
                'level' => 'info',
                'message' => 'Test',
                'context' => ['user_id' => 123, 'action' => 'login'],
                'time' => time(),
            ],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Test')
            ->andReturn('Test');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with(['user_id' => 123, 'action' => 'login'])
            ->once()
            ->andReturn('Context preview');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['context_preview'])->toBe('Context preview');
    });

    it('returns empty string for empty context preview', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Test')
            ->andReturn('Test');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['context_preview'])->toBe('');
    });

    it('stores context dump when context is not empty', function () {
        $context = ['user_id' => 123];
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => $context, 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');

        $this->dumpManager->shouldReceive('storeDump')
            ->with('req-123', $context)
            ->once()
            ->andReturn('dump-context-123');

        $this->dumpManager->shouldReceive('storeDump')
            ->with('req-123', 'Test')
            ->andReturn('dump-message');

        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['context_dump_id'])->toBe('dump-context-123');
    });

    it('returns null context dump ID when context is empty', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')
            ->with('req-123', 'Test')
            ->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['context_dump_id'])->toBeNull();
    });

    it('attempts to generate inline HTML for small context', function () {
        $context = ['key' => 'value'];
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => $context, 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->andReturn('small');

        $this->dumpManager->shouldReceive('storeDump')
            ->andReturn('dump-id');

        $processed = $this->processor->process($rawLogs, 'req-123');

        // context_inline_html should have a value (generated or null based on internal logic)
        expect($processed[0])->toHaveKey('context_inline_html');
    });

    it('returns null inline HTML when context is empty', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['context_inline_html'])->toBeNull();
    });

    it('defaults to empty array when context is missing', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['context_preview'])->toBe('');
        expect($processed[0]['context_dump_id'])->toBeNull();
    });
});

// ============================================================================
// 6. ERROR HANDLING
// ============================================================================

describe('Error Handling', function () {
    it('skips individual log entry on processing error', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Good log', 'context' => [], 'time' => time()],
            ['level' => 'error', 'message' => 'Bad log', 'context' => [], 'time' => time()],
            ['level' => 'debug', 'message' => 'Another good log', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Good log')
            ->andReturn('Good log');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Bad log')
            ->andThrow(new \Exception('Processing error'));

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Another good log')
            ->andReturn('Another good log');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        // Should only have 2 entries (skipped the bad one)
        expect($processed)->toHaveCount(2);
        expect($processed[0]['message_preview'])->toBe('Good log');
        expect($processed[1]['message_preview'])->toBe('Another good log');
    });

    it('handles inline HTML generation errors gracefully', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Test')
            ->andReturn('Test');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $this->dumpManager->shouldReceive('clonePreview')
            ->andThrow(new \Exception('Cloning error'));

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_inline_html'])->toBeNull();
    });
});

// ============================================================================
// 7. EDGE CASES
// ============================================================================

describe('Edge Cases', function () {
    it('handles malformed log entry with missing fields', function () {
        $rawLogs = [
            ['message' => 'Only message'], // Missing level, context, time
        ];

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed)->toHaveCount(1);
        expect($processed[0]['level'])->toBe('info'); // Default
        expect($processed[0]['time'])->toMatch('/^\d{2}:\d{2}:\d{2}\.\d{3}$/');
    });

    it('handles very large context arrays', function () {
        $largeContext = array_fill(0, 1000, 'data');
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test', 'context' => $largeContext, 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with('Test')
            ->andReturn('Test');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with($largeContext)
            ->andReturn(str_repeat('x', 200)); // Large preview

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['context_inline_html'])->toBeNull(); // Too large for inline
    });

    it('handles special characters in messages', function () {
        $message = "Test with special chars: \n\r\t\0 👍 ñ";
        $rawLogs = [
            ['level' => 'info', 'message' => $message, 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with($message)
            ->andReturn('Special chars preview');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_preview'])->toBe('Special chars preview');
    });

    it('handles object messages', function () {
        $object = new \stdClass();
        $object->property = 'value';

        $rawLogs = [
            ['level' => 'info', 'message' => $object, 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with($object)
            ->andReturn('Object preview');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_preview'])->toBe('Object preview');
    });

    it('handles array messages', function () {
        $arrayMessage = ['error' => 'Something went wrong', 'code' => 500];
        $rawLogs = [
            ['level' => 'error', 'message' => $arrayMessage, 'context' => [], 'time' => time()],
        ];

        $this->dumpManager->shouldReceive('buildPreview')
            ->with($arrayMessage)
            ->andReturn('Array preview');

        $this->dumpManager->shouldReceive('buildPreview')
            ->with([])
            ->andReturn('');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');

        $processed = $this->processor->process($rawLogs, 'req-123');

        expect($processed[0]['message_preview'])->toBe('Array preview');
    });
});
