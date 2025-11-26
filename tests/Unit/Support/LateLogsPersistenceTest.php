<?php

namespace ThiagoVieira\Saci\Tests\Unit\Support;

use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\Support\DumpStorage;
use Mockery;

beforeEach(function () {
    $this->storage = Mockery::mock(DumpStorage::class);
    $this->persistence = new LateLogsPersistence($this->storage);
});

afterEach(function () {
    Mockery::close();
});

// ============================================================================
// 1. PERSIST FUNCTIONALITY
// ============================================================================

describe('Persist Functionality', function () {
    it('persists late logs successfully', function () {
        $lateLogs = [
            ['level' => 'info', 'message' => 'Late log 1'],
            ['level' => 'debug', 'message' => 'Late log 2'],
        ];

        $expectedJson = json_encode([
            'logs' => $lateLogs,
            'count' => 2,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->storage->shouldReceive('storeHtml')
            ->once()
            ->with('req-123', '__late_logs', $expectedJson);

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeTrue();
    });

    it('returns false for empty logs array', function () {
        $result = $this->persistence->persist('req-123', []);

        expect($result)->toBeFalse();
    });

    it('stores logs with correct structure', function () {
        $lateLogs = [
            ['level' => 'error', 'message' => 'Error occurred', 'context' => ['code' => 500]],
        ];

        $this->storage->shouldReceive('storeHtml')
            ->once()
            ->with('req-123', '__late_logs', Mockery::on(function ($json) use ($lateLogs) {
                $decoded = json_decode($json, true);
                return $decoded['logs'] === $lateLogs && $decoded['count'] === 1;
            }));

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeTrue();
    });

    it('uses JSON_UNESCAPED_UNICODE flag', function () {
        $lateLogs = [
            ['level' => 'info', 'message' => 'Mensagem com acentuação: José'],
        ];

        $this->storage->shouldReceive('storeHtml')
            ->once()
            ->with('req-123', '__late_logs', Mockery::on(function ($json) {
                // Should NOT have escaped unicode (e.g., \u00e9 for é)
                return str_contains($json, 'José') && !str_contains($json, '\\u');
            }));

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeTrue();
    });

    it('uses JSON_UNESCAPED_SLASHES flag', function () {
        $lateLogs = [
            ['level' => 'info', 'message' => 'Path: /var/www/app'],
        ];

        $this->storage->shouldReceive('storeHtml')
            ->once()
            ->with('req-123', '__late_logs', Mockery::on(function ($json) {
                // Should NOT have escaped slashes (e.g., \/ for /)
                return str_contains($json, '/var/www/app') && !str_contains($json, '\\/');
            }));

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeTrue();
    });

    it('returns false on JSON encoding failure', function () {
        // Create a resource (not JSON serializable)
        $resource = fopen('php://memory', 'r');

        $lateLogs = [
            ['level' => 'info', 'resource' => $resource],
        ];

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeFalse();

        fclose($resource);
    });

    it('returns false on storage exception', function () {
        $lateLogs = [
            ['level' => 'info', 'message' => 'Test'],
        ];

        $this->storage->shouldReceive('storeHtml')
            ->andThrow(new \Exception('Storage error'));

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeFalse();
    });
});

// ============================================================================
// 2. RETRIEVE FUNCTIONALITY
// ============================================================================

describe('Retrieve Functionality', function () {
    it('retrieves persisted late logs', function () {
        $lateLogs = [
            ['level' => 'info', 'message' => 'Late log'],
        ];

        $json = json_encode([
            'logs' => $lateLogs,
            'count' => 1,
        ]);

        $this->storage->shouldReceive('getHtml')
            ->once()
            ->with('req-123', '__late_logs')
            ->andReturn($json);

        $result = $this->persistence->retrieve('req-123');

        expect($result)->toHaveKeys(['logs', 'count']);
        expect($result['logs'])->toBe($lateLogs);
        expect($result['count'])->toBe(1);
    });

    it('returns empty structure when no logs found', function () {
        $this->storage->shouldReceive('getHtml')
            ->once()
            ->with('req-123', '__late_logs')
            ->andReturn(null);

        $result = $this->persistence->retrieve('req-123');

        expect($result)->toBe(['logs' => [], 'count' => 0]);
    });

    it('returns empty structure on invalid JSON', function () {
        $this->storage->shouldReceive('getHtml')
            ->once()
            ->with('req-123', '__late_logs')
            ->andReturn('invalid json {{{');

        $result = $this->persistence->retrieve('req-123');

        expect($result)->toBe(['logs' => [], 'count' => 0]);
    });

    it('returns empty structure when decoded data is not array', function () {
        $this->storage->shouldReceive('getHtml')
            ->once()
            ->with('req-123', '__late_logs')
            ->andReturn('"string data"'); // Valid JSON but not array

        $result = $this->persistence->retrieve('req-123');

        expect($result)->toBe(['logs' => [], 'count' => 0]);
    });

    it('returns empty structure on storage exception', function () {
        $this->storage->shouldReceive('getHtml')
            ->andThrow(new \Exception('Storage error'));

        $result = $this->persistence->retrieve('req-123');

        expect($result)->toBe(['logs' => [], 'count' => 0]);
    });

    it('handles Unicode characters in retrieved logs', function () {
        $lateLogs = [
            ['level' => 'info', 'message' => 'Unicode: 你好 мир'],
        ];

        $json = json_encode(['logs' => $lateLogs, 'count' => 1]);

        $this->storage->shouldReceive('getHtml')
            ->with('req-123', '__late_logs')
            ->andReturn($json);

        $result = $this->persistence->retrieve('req-123');

        expect($result['logs'][0]['message'])->toBe('Unicode: 你好 мир');
    });
});

// ============================================================================
// 3. INTEGRATION (PERSIST + RETRIEVE)
// ============================================================================

describe('Integration', function () {
    it('persists and retrieves logs correctly', function () {
        $lateLogs = [
            ['level' => 'warning', 'message' => 'Warning message', 'timestamp' => time()],
            ['level' => 'error', 'message' => 'Error message', 'timestamp' => time()],
        ];

        $expectedJson = json_encode([
            'logs' => $lateLogs,
            'count' => 2,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->storage->shouldReceive('storeHtml')
            ->once()
            ->with('req-123', '__late_logs', $expectedJson);

        $this->storage->shouldReceive('getHtml')
            ->once()
            ->with('req-123', '__late_logs')
            ->andReturn($expectedJson);

        // Persist
        $persistResult = $this->persistence->persist('req-123', $lateLogs);
        expect($persistResult)->toBeTrue();

        // Retrieve
        $retrieved = $this->persistence->retrieve('req-123');

        expect($retrieved['logs'])->toBe($lateLogs);
        expect($retrieved['count'])->toBe(2);
    });
});

// ============================================================================
// 4. EDGE CASES
// ============================================================================

describe('Edge Cases', function () {
    it('handles very large log arrays', function () {
        $lateLogs = array_fill(0, 1000, [
            'level' => 'info',
            'message' => 'Log message',
        ]);

        $this->storage->shouldReceive('storeHtml')
            ->once()
            ->with('req-123', '__late_logs', Mockery::type('string'));

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeTrue();
    });

    it('handles logs with special characters', function () {
        $lateLogs = [
            ['level' => 'info', 'message' => "Test\n\r\t\"'<>&"],
        ];

        $this->storage->shouldReceive('storeHtml')
            ->once();

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeTrue();
    });

    it('handles logs with nested arrays', function () {
        $lateLogs = [
            [
                'level' => 'info',
                'message' => 'Test',
                'context' => [
                    'nested' => [
                        'deep' => [
                            'data' => 'value',
                        ],
                    ],
                ],
            ],
        ];

        $this->storage->shouldReceive('storeHtml')
            ->once();

        $result = $this->persistence->persist('req-123', $lateLogs);

        expect($result)->toBeTrue();
    });

    it('handles empty request ID', function () {
        $lateLogs = [['level' => 'info', 'message' => 'Test']];

        $this->storage->shouldReceive('storeHtml')
            ->once()
            ->with('', '__late_logs', Mockery::type('string'));

        $result = $this->persistence->persist('', $lateLogs);

        expect($result)->toBeTrue();
    });
});
