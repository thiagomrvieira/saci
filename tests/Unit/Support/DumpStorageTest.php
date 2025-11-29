<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Support\DumpStorage;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Use a test disk
    config()->set('filesystems.disks.test_saci', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/saci'),
    ]);

    $this->storage = new DumpStorage('test_saci', 1048576, 60);

    // Clean up before each test
    Storage::disk('test_saci')->deleteDirectory('saci');
});

afterEach(function () {
    // Clean up after each test
    Storage::disk('test_saci')->deleteDirectory('saci');
});

describe('DumpStorage ID Generation', function () {
    it('generates valid request ID', function () {
        $requestId = $this->storage->generateRequestId();

        expect($requestId)->toBeValidRequestId();
    });

    it('generates unique request IDs', function () {
        $id1 = $this->storage->generateRequestId();
        $id2 = $this->storage->generateRequestId();

        expect($id1)->not->toBe($id2);
    });

    it('generates valid dump ID', function () {
        $dumpId = $this->storage->generateDumpId();

        expect($dumpId)
            ->toBeString()
            ->toHaveLength(12);
    });

    it('generates unique dump IDs', function () {
        $id1 = $this->storage->generateDumpId();
        $id2 = $this->storage->generateDumpId();

        expect($id1)->not->toBe($id2);
    });
});

describe('DumpStorage Path Management', function () {
    it('generates correct base path', function () {
        $requestId = 'test-request-id';
        $path = $this->storage->basePath($requestId);

        expect($path)->toBe('saci/dumps/test-request-id');
    });

    it('generates different paths for different request IDs', function () {
        $path1 = $this->storage->basePath('request-1');
        $path2 = $this->storage->basePath('request-2');

        expect($path1)->not->toBe($path2);
    });
});

describe('DumpStorage HTML Storage', function () {
    it('stores HTML dump successfully', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = $this->storage->generateDumpId();
        $html = '<div>Test dump content</div>';

        $result = $this->storage->storeHtml($requestId, $dumpId, $html);

        expect($result)->toBeTrue();
    });

    it('creates directory if it does not exist', function () {
        $disk = Storage::disk('test_saci');
        $requestId = $this->storage->generateRequestId();
        $basePath = $this->storage->basePath($requestId);

        expect($disk->exists($basePath))->toBeFalse();

        $this->storage->storeHtml($requestId, 'test', '<div>test</div>');

        expect($disk->exists($basePath))->toBeTrue();
    });

    it('stores HTML with correct filename', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = 'test-dump-id';
        $html = '<div>Test content</div>';

        $this->storage->storeHtml($requestId, $dumpId, $html);

        $disk = Storage::disk('test_saci');
        $filePath = $this->storage->basePath($requestId) . '/' . $dumpId . '.html';

        expect($disk->exists($filePath))->toBeTrue();
    });

    it('stores multiple dumps for same request', function () {
        $requestId = $this->storage->generateRequestId();

        $result1 = $this->storage->storeHtml($requestId, 'dump1', '<div>First</div>');
        $result2 = $this->storage->storeHtml($requestId, 'dump2', '<div>Second</div>');

        expect($result1)->toBeTrue();
        expect($result2)->toBeTrue();
    });
});

describe('DumpStorage HTML Retrieval', function () {
    it('retrieves stored HTML', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = $this->storage->generateDumpId();
        $html = '<div class="test">Test dump content</div>';

        $this->storage->storeHtml($requestId, $dumpId, $html);
        $retrieved = $this->storage->getHtml($requestId, $dumpId);

        expect($retrieved)->toBe($html);
    });

    it('returns null for non-existent dump', function () {
        $retrieved = $this->storage->getHtml('nonexistent-request', 'nonexistent-dump');

        expect($retrieved)->toBeNull();
    });

    it('returns null for wrong request ID', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = $this->storage->generateDumpId();

        $this->storage->storeHtml($requestId, $dumpId, '<div>test</div>');
        $retrieved = $this->storage->getHtml('wrong-request-id', $dumpId);

        expect($retrieved)->toBeNull();
    });

    it('returns null for wrong dump ID', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = $this->storage->generateDumpId();

        $this->storage->storeHtml($requestId, $dumpId, '<div>test</div>');
        $retrieved = $this->storage->getHtml($requestId, 'wrong-dump-id');

        expect($retrieved)->toBeNull();
    });
});

describe('DumpStorage Byte Cap Enforcement', function () {
    it('enforces per-request byte cap', function () {
        $storage = new DumpStorage('test_saci', 100, 60); // 100 bytes cap
        $requestId = $storage->generateRequestId();

        // First dump: 50 bytes (should succeed)
        $result1 = $storage->storeHtml($requestId, 'dump1', str_repeat('x', 50));
        expect($result1)->toBeTrue();

        // Second dump: 40 bytes (should succeed, total 90)
        $result2 = $storage->storeHtml($requestId, 'dump2', str_repeat('y', 40));
        expect($result2)->toBeTrue();

        // Third dump: 20 bytes (should fail, would exceed 100)
        $result3 = $storage->storeHtml($requestId, 'dump3', str_repeat('z', 20));
        expect($result3)->toBeFalse();
    });

    it('allows storage up to cap limit', function () {
        $storage = new DumpStorage('test_saci', 100, 60);
        $requestId = $storage->generateRequestId();

        // Exactly at limit
        $result = $storage->storeHtml($requestId, 'dump1', str_repeat('x', 100));

        expect($result)->toBeTrue();
    });

    it('rejects storage exceeding cap', function () {
        $storage = new DumpStorage('test_saci', 100, 60);
        $requestId = $storage->generateRequestId();

        // Over limit
        $result = $storage->storeHtml($requestId, 'dump1', str_repeat('x', 101));

        expect($result)->toBeFalse();
    });

    it('applies cap per request independently', function () {
        $storage = new DumpStorage('test_saci', 100, 60);

        $request1 = $storage->generateRequestId();
        $request2 = $storage->generateRequestId();

        // Fill request 1
        $result1 = $storage->storeHtml($request1, 'dump1', str_repeat('x', 100));
        expect($result1)->toBeTrue();

        // Request 2 should have its own cap
        $result2 = $storage->storeHtml($request2, 'dump1', str_repeat('y', 100));
        expect($result2)->toBeTrue();
    });
});

describe('DumpStorage Cleanup', function () {
    it('cleans up expired dumps', function () {
        $storage = new DumpStorage('test_saci', 1048576, 1); // 1 second TTL
        $requestId = $storage->generateRequestId();
        $dumpId = $storage->generateDumpId();

        // Store a dump
        $storage->storeHtml($requestId, $dumpId, '<div>test</div>');

        // Verify it exists
        expect($storage->getHtml($requestId, $dumpId))->not->toBeNull();

        // Wait for TTL to expire
        sleep(2);

        // Run cleanup
        $storage->cleanupExpired();

        // Dump should be deleted
        expect($storage->getHtml($requestId, $dumpId))->toBeNull();
    });

    it('keeps non-expired dumps', function () {
        $storage = new DumpStorage('test_saci', 1048576, 60); // 60 seconds TTL
        $requestId = $storage->generateRequestId();
        $dumpId = $storage->generateDumpId();

        $storage->storeHtml($requestId, $dumpId, '<div>test</div>');
        $storage->cleanupExpired();

        // Should still exist
        expect($storage->getHtml($requestId, $dumpId))->not->toBeNull();
    });

    it('handles cleanup with no dumps gracefully', function () {
        expect(fn() => $this->storage->cleanupExpired())->not->toThrow(Exception::class);
    });
});

describe('DumpStorage Edge Cases', function () {
    it('handles empty HTML', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = $this->storage->generateDumpId();

        $result = $this->storage->storeHtml($requestId, $dumpId, '');

        expect($result)->toBeTrue();
        expect($this->storage->getHtml($requestId, $dumpId))->toBe('');
    });

    it('handles large HTML within cap', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = $this->storage->generateDumpId();
        $largeHtml = str_repeat('<div>content</div>', 1000); // ~18KB

        $result = $this->storage->storeHtml($requestId, $dumpId, $largeHtml);

        expect($result)->toBeTrue();
        expect($this->storage->getHtml($requestId, $dumpId))->toBe($largeHtml);
    });

    it('handles special characters in HTML', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = $this->storage->generateDumpId();
        $html = '<div>Special: "quotes" & \'apostrophes\' < > é ñ 中文</div>';

        $this->storage->storeHtml($requestId, $dumpId, $html);
        $retrieved = $this->storage->getHtml($requestId, $dumpId);

        expect($retrieved)->toBe($html);
    });

    it('overwrites dump with same ID', function () {
        $requestId = $this->storage->generateRequestId();
        $dumpId = 'same-id';

        $this->storage->storeHtml($requestId, $dumpId, '<div>First</div>');
        $this->storage->storeHtml($requestId, $dumpId, '<div>Second</div>');

        $retrieved = $this->storage->getHtml($requestId, $dumpId);

        expect($retrieved)->toBe('<div>Second</div>');
    });
});

describe('DumpStorage Configuration', function () {
    it('uses custom disk', function () {
        config()->set('filesystems.disks.custom_disk', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/custom'),
        ]);

        $storage = new DumpStorage('custom_disk', 1048576, 60);
        $requestId = $storage->generateRequestId();
        $dumpId = $storage->generateDumpId();

        $storage->storeHtml($requestId, $dumpId, '<div>test</div>');

        $disk = Storage::disk('custom_disk');
        $filePath = $storage->basePath($requestId) . '/' . $dumpId . '.html';

        expect($disk->exists($filePath))->toBeTrue();

        // Cleanup
        $disk->deleteDirectory('saci');
    });

    it('respects custom byte cap', function () {
        $storage = new DumpStorage('test_saci', 50, 60); // 50 bytes cap
        $requestId = $storage->generateRequestId();

        $result1 = $storage->storeHtml($requestId, 'dump1', str_repeat('x', 50));
        expect($result1)->toBeTrue();

        $result2 = $storage->storeHtml($requestId, 'dump2', 'x');
        expect($result2)->toBeFalse();
    });

    it('respects custom TTL', function () {
        $storage = new DumpStorage('test_saci', 1048576, 1); // 1 second TTL
        $requestId = $storage->generateRequestId();

        $storage->storeHtml($requestId, 'dump1', '<div>test</div>');

        sleep(2);
        $storage->cleanupExpired();

        expect($storage->getHtml($requestId, 'dump1'))->toBeNull();
    });

    // Note: Lines 108-109 and 117-118 (exception handling in lastModified/filesize)
    // are difficult to test in isolation as they require mocking Storage facade deeply.
    // These are covered indirectly through integration tests where disk operations may fail.
});



