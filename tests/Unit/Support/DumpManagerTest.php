<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\DumpStorage;
use Symfony\Component\VarDumper\Cloner\Data;

beforeEach(function () {
    $this->storage = Mockery::mock(DumpStorage::class);
    $this->manager = new DumpManager($this->storage);
});

describe('DumpManager Cloning', function () {
    it('clones a value to Data object', function () {
        $value = ['test' => 'data'];
        $data = $this->manager->clone($value);

        expect($data)->toBeInstanceOf(Data::class);
    });

    it('clones preview with limits', function () {
        $value = ['test' => 'data'];
        $data = $this->manager->clonePreview($value);

        expect($data)->toBeInstanceOf(Data::class);
    });

    it('clones different data types', function (mixed $value) {
        $data = $this->manager->clone($value);

        expect($data)->toBeInstanceOf(Data::class);
    })->with([
        'string' => ['hello world'],
        'integer' => [42],
        'float' => [3.14],
        'boolean' => [true],
        'null' => [null],
        'array' => [['a' => 1, 'b' => 2]],
        'object' => [new stdClass()],
    ]);
});

describe('DumpManager HTML Rendering', function () {
    it('renders HTML from Data', function () {
        $data = $this->manager->clone(['test' => 'value']);
        $html = $this->manager->renderHtml($data);

        expect($html)
            ->toBeString()
            ->not->toBeEmpty();
    });

    it('renders expanded HTML', function () {
        $data = $this->manager->clone(['nested' => ['data' => 'here']]);
        $html = $this->manager->renderHtmlExpanded($data);

        expect($html)
            ->toBeString()
            ->toContain('sf-dump-expanded');
    });

    it('removes inline scripts from expanded HTML', function () {
        $data = $this->manager->clone(['test' => 'value']);
        $html = $this->manager->renderHtmlExpanded($data);

        expect($html)->not->toContain('<script');
    });

    it('removes inline styles from expanded HTML', function () {
        $data = $this->manager->clone(['test' => 'value']);
        $html = $this->manager->renderHtmlExpanded($data);

        expect($html)->not->toContain('<style');
    });

    it('renders different data types as HTML', function (mixed $value) {
        $data = $this->manager->clone($value);
        $html = $this->manager->renderHtml($data);

        expect($html)->toBeString()->not->toBeEmpty();
    })->with([
        'array' => [['key' => 'value']],
        'string' => ['test string'],
        'number' => [123],
        'object' => [new stdClass()],
    ]);
});

describe('DumpManager Preview Generation', function () {
    it('builds preview string', function () {
        $value = ['test' => 'data'];
        $preview = $this->manager->buildPreview($value);

        expect($preview)->toBeString();
    });

    it('truncates long previews', function () {
        $longString = str_repeat('x', 200);
        $preview = $this->manager->buildPreview($longString);

        expect(mb_strlen($preview))->toBeLessThanOrEqual(71); // 70 + ellipsis
    });

    it('adds ellipsis to truncated previews', function () {
        $longString = str_repeat('x', 200);
        $preview = $this->manager->buildPreview($longString);

        expect($preview)->toEndWith('…');
    });

    it('does not truncate short values', function () {
        $shortValue = 'short';
        $preview = $this->manager->buildPreview($shortValue);

        expect($preview)->not->toContain('…');
    });

    it('generates single-line preview', function () {
        $multiline = "line1\nline2\nline3";
        $preview = $this->manager->buildPreview($multiline);

        expect($preview)->not->toContain("\n");
    });

    it('builds preview for various data types', function (mixed $value) {
        $preview = $this->manager->buildPreview($value);

        expect($preview)->toBeString();
    })->with([
        'string' => ['test'],
        'array' => [['a' => 1]],
        'object' => [new stdClass()],
        'null' => [null],
        'boolean' => [true],
        'number' => [42],
    ]);
});

describe('DumpManager Store Dump', function () {
    it('stores dump successfully', function () {
        $requestId = 'test-request-id';
        $value = ['test' => 'data'];

        $this->storage
            ->shouldReceive('generateDumpId')
            ->once()
            ->andReturn('dump-id-123');

        $this->storage
            ->shouldReceive('storeHtml')
            ->once()
            ->with($requestId, 'dump-id-123', Mockery::type('string'))
            ->andReturn(true);

        $dumpId = $this->manager->storeDump($requestId, $value);

        expect($dumpId)->toBe('dump-id-123');
    });

    it('returns null when storage fails', function () {
        $requestId = 'test-request-id';
        $value = ['test' => 'data'];

        $this->storage
            ->shouldReceive('generateDumpId')
            ->once()
            ->andReturn('dump-id-123');

        $this->storage
            ->shouldReceive('storeHtml')
            ->once()
            ->andReturn(false); // Storage failed (e.g., cap exceeded)

        $dumpId = $this->manager->storeDump($requestId, $value);

        expect($dumpId)->toBeNull();
    });

    it('stores expanded HTML', function () {
        $requestId = 'test-request-id';
        $value = ['nested' => ['data' => 'here']];

        $this->storage
            ->shouldReceive('generateDumpId')
            ->andReturn('dump-id');

        $this->storage
            ->shouldReceive('storeHtml')
            ->with($requestId, 'dump-id', Mockery::on(function ($html) {
                return str_contains($html, 'sf-dump-expanded')
                    && !str_contains($html, '<script')
                    && !str_contains($html, '<style');
            }))
            ->andReturn(true);

        $dumpId = $this->manager->storeDump($requestId, $value);

        expect($dumpId)->toBe('dump-id');
    });
});

describe('DumpManager Configuration', function () {
    it('respects custom limits', function () {
        $storage = Mockery::mock(DumpStorage::class);
        $limits = [
            'max_depth' => 10,
            'max_items' => 500,
            'max_string' => 5000,
            'preview_max_items' => 5,
            'preview_max_string' => 50,
            'preview_max_chars' => 40,
        ];

        $manager = new DumpManager($storage, $limits);

        // Test that preview respects character limit
        $longString = str_repeat('x', 100);
        $preview = $manager->buildPreview($longString);

        expect(mb_strlen($preview))->toBeLessThanOrEqual(41); // 40 + ellipsis
    });

    it('uses default limits when not specified', function () {
        $storage = Mockery::mock(DumpStorage::class);
        $manager = new DumpManager($storage);

        $value = ['test' => 'data'];
        $preview = $manager->buildPreview($value);

        expect($preview)->toBeString();
    });

    it('handles partial limit configuration', function () {
        $storage = Mockery::mock(DumpStorage::class);
        $limits = [
            'max_items' => 200,
            // Other limits will use defaults
        ];

        $manager = new DumpManager($storage, $limits);
        $preview = $manager->buildPreview(['test' => 'data']);

        expect($preview)->toBeString();
    });
});

describe('DumpManager Edge Cases', function () {
    it('handles empty values', function () {
        $data = $this->manager->clone('');
        $html = $this->manager->renderHtml($data);

        expect($html)->toBeString();
    });

    it('handles deeply nested structures', function () {
        $nested = ['level1' => ['level2' => ['level3' => ['level4' => 'deep']]]];
        $data = $this->manager->clone($nested);
        $html = $this->manager->renderHtml($data);

        expect($html)->toBeString()->not->toBeEmpty();
    });

    it('handles circular references', function () {
        $obj = new stdClass();
        $obj->self = $obj; // Circular reference

        $data = $this->manager->clone($obj);
        $html = $this->manager->renderHtml($data);

        expect($html)->toBeString()->not->toBeEmpty();
    });

    it('handles large arrays', function () {
        $largeArray = array_fill(0, 1000, 'value');
        $data = $this->manager->clone($largeArray);
        $html = $this->manager->renderHtml($data);

        expect($html)->toBeString()->not->toBeEmpty();
    });

    it('handles special characters', function () {
        $value = ['special' => "quotes \"' & < > \n \t"];
        $data = $this->manager->clone($value);
        $html = $this->manager->renderHtml($data);

        expect($html)->toBeString()->not->toBeEmpty();
    });

    it('handles unicode characters', function () {
        $value = ['unicode' => '🔍 中文 العربية עברית'];
        $data = $this->manager->clone($value);
        $preview = $this->manager->buildPreview($value);

        expect($preview)->toBeString();
    });

    it('handles null values', function () {
        $data = $this->manager->clone(null);
        $html = $this->manager->renderHtml($data);

        expect($html)->toBeString();
    });

    it('handles resources', function () {
        $resource = fopen('php://memory', 'r');
        $data = $this->manager->clone($resource);
        $html = $this->manager->renderHtml($data);
        fclose($resource);

        expect($html)->toBeString();
    });
});

describe('DumpManager Integration', function () {
    it('completes full dump workflow', function () {
        $storage = Mockery::mock(DumpStorage::class);
        $manager = new DumpManager($storage, [
            'preview_max_chars' => 50,
        ]);

        $value = ['name' => 'John Doe', 'age' => 30, 'email' => 'john@example.com'];

        // 1. Clone value
        $data = $manager->clone($value);
        expect($data)->toBeInstanceOf(Data::class);

        // 2. Render HTML
        $html = $manager->renderHtmlExpanded($data);
        expect($html)->toBeString()->not->toBeEmpty();

        // 3. Build preview
        $preview = $manager->buildPreview($value);
        expect($preview)->toBeString();

        // 4. Store dump
        $storage->shouldReceive('generateDumpId')->andReturn('dump-123');
        $storage->shouldReceive('storeHtml')->andReturn(true);

        $dumpId = $manager->storeDump('request-123', $value);
        expect($dumpId)->toBe('dump-123');
    });

    it('handles mixed data types in workflow', function () {
        $storage = Mockery::mock(DumpStorage::class);
        $manager = new DumpManager($storage);

        $complexValue = [
            'string' => 'text',
            'number' => 42,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => new stdClass(),
        ];

        // Clone and render
        $data = $manager->clone($complexValue);
        $html = $manager->renderHtmlExpanded($data);

        expect($html)
            ->toBeString()
            ->not->toBeEmpty()
            ->not->toContain('<script');

        // Build preview
        $preview = $manager->buildPreview($complexValue);
        expect($preview)->toBeString();
    });
});



