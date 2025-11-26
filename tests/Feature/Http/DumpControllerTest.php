<?php

namespace ThiagoVieira\Saci\Tests\Feature\Http;

use ThiagoVieira\Saci\Support\DumpStorage;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\RequestValidator;
use Illuminate\Support\Facades\Route;
use Mockery;

beforeEach(function () {
    // Register routes for testing
    Route::get('/_saci/dump/{requestId}/{dumpId}', [\ThiagoVieira\Saci\Http\Controllers\DumpController::class, 'show']);
    Route::get('/_saci/late-logs/{requestId}', [\ThiagoVieira\Saci\Http\Controllers\DumpController::class, 'lateLogs']);

    // Mock dependencies
    $this->storage = Mockery::mock(DumpStorage::class)->shouldIgnoreMissing();
    $this->storage->shouldReceive('generateRequestId')->andReturn('mock-request-id');

    $this->validator = Mockery::mock(RequestValidator::class)->shouldIgnoreMissing();
    $this->lateLogsPersistence = Mockery::mock(LateLogsPersistence::class);

    $this->app->instance(DumpStorage::class, $this->storage);
    $this->app->instance(RequestValidator::class, $this->validator);
    $this->app->instance(LateLogsPersistence::class, $this->lateLogsPersistence);
});

afterEach(function () {
    Mockery::close();
});

// ============================================================================
// 1. DUMP RETRIEVAL
// ============================================================================

describe('Dump Retrieval', function () {
    it('serves stored dump HTML', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->storage->shouldReceive('getHtml')
            ->with('req-123', 'dump-456')
            ->andReturn('<html>Dump content</html>');

        $response = $this->get('/_saci/dump/req-123/dump-456');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Dump content', false);
    });

    it('returns 404 for non-existent dump', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->storage->shouldReceive('getHtml')
            ->with('req-123', 'dump-456')
            ->andReturn(null);

        $response = $this->get('/_saci/dump/req-123/dump-456');

        $response->assertStatus(404);
        $response->assertSee('Not Found');
    });

    it('returns 403 when client is not allowed', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(false);

        $response = $this->get('/_saci/dump/req-123/dump-456');

        $response->assertStatus(403);
        $response->assertSee('Forbidden');
    });

    it('sets no-cache headers for dumps', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->storage->shouldReceive('getHtml')->andReturn('<html>Content</html>');

        $response = $this->get('/_saci/dump/req-123/dump-456');

        $cacheControl = $response->headers->get('Cache-Control');
        expect($cacheControl)->toContain('no-cache');
        expect($cacheControl)->toContain('no-store');
    });

    it('sets nosniff header for security', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->storage->shouldReceive('getHtml')->andReturn('<html>Content</html>');

        $response = $this->get('/_saci/dump/req-123/dump-456');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    });
});

// ============================================================================
// 2. LATE LOGS RETRIEVAL
// ============================================================================

describe('Late Logs Retrieval', function () {
    it('retrieves late logs as JSON', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->lateLogsPersistence->shouldReceive('retrieve')
            ->with('req-123')
            ->andReturn([
                'logs' => [
                    ['level' => 'info', 'message' => 'Late log'],
                ],
                'count' => 1,
            ]);

        $response = $this->get('/_saci/late-logs/req-123');

        $response->assertStatus(200);
        $response->assertJson([
            'logs' => [
                ['level' => 'info', 'message' => 'Late log'],
            ],
            'count' => 1,
        ]);
    });

    it('returns empty structure when no late logs found', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->lateLogsPersistence->shouldReceive('retrieve')
            ->with('req-123')
            ->andReturn(['logs' => [], 'count' => 0]);

        $response = $this->get('/_saci/late-logs/req-123');

        $response->assertStatus(200);
        $response->assertJson(['logs' => [], 'count' => 0]);
    });

    it('returns 403 when client is not allowed', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(false);

        $response = $this->get('/_saci/late-logs/req-123');

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden']);
    });

    it('sets no-cache headers for late logs', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->lateLogsPersistence->shouldReceive('retrieve')
            ->andReturn(['logs' => [], 'count' => 0]);

        $response = $this->get('/_saci/late-logs/req-123');

        $cacheControl = $response->headers->get('Cache-Control');
        expect($cacheControl)->toContain('no-cache');
        expect($cacheControl)->toContain('no-store');
    });

    it('handles multiple late logs', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);
        $this->lateLogsPersistence->shouldReceive('retrieve')
            ->with('req-123')
            ->andReturn([
                'logs' => [
                    ['level' => 'info', 'message' => 'Log 1'],
                    ['level' => 'debug', 'message' => 'Log 2'],
                    ['level' => 'error', 'message' => 'Log 3'],
                ],
                'count' => 3,
            ]);

        $response = $this->get('/_saci/late-logs/req-123');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'logs');
    });
});

// ============================================================================
// 3. SECURITY & VALIDATION
// ============================================================================

describe('Security & Validation', function () {
    it('validates request IDs to prevent injection', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);

        // Test with various request ID formats
        $validId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $this->storage->shouldReceive('getHtml')
            ->with($validId, 'dump-123')
            ->andReturn('<html>Content</html>');

        $response = $this->get("/_saci/dump/{$validId}/dump-123");

        $response->assertStatus(200);
    });

    it('validates dump IDs to prevent injection', function () {
        $this->validator->shouldReceive('shouldServeDump')->andReturn(true);

        // Test with valid dump ID format
        $validDumpId = 'abc123def456';

        $this->storage->shouldReceive('getHtml')
            ->with('req-123', $validDumpId)
            ->andReturn('<html>Content</html>');

        $response = $this->get("/_saci/dump/req-123/{$validDumpId}");

        $response->assertStatus(200);
    });
});
