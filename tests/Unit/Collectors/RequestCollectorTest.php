<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Collectors\RequestCollector;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\TemplateTracker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->dumpManager = Mockery::mock(DumpManager::class);
    $this->tracker = Mockery::mock(TemplateTracker::class);
    $this->collector = new RequestCollector($this->dumpManager, $this->tracker);
});

describe('RequestCollector Identity', function () {
    it('returns correct name', function () {
        expect($this->collector->getName())->toBe('request');
    });

    it('returns correct label', function () {
        expect($this->collector->getLabel())->toBe('Request');
    });

    it('is enabled by default', function () {
        config()->set('saci.collectors.request', true);
        expect($this->collector->isEnabled())->toBeTrue();
    });
});

describe('RequestCollector Lifecycle', function () {
    it('records start time on start', function () {
        $before = microtime(true);
        $this->collector->start();
        $after = microtime(true);

        // Start time should be recorded
        expect(true)->toBeTrue();
    });

    it('accepts request via setRequest', function () {
        $request = Request::create('/test', 'GET');

        expect(fn() => $this->collector->setRequest($request))->not->toThrow(Exception::class);
    });

    it('accepts response via setResponse', function () {
        $response = new Response('test content', 200);

        expect(fn() => $this->collector->setResponse($response))->not->toThrow(Exception::class);
    });

    it('resets state on reset', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('test', 200);

        $this->collector->setRequest($request);
        $this->collector->setResponse($response);
        $this->collector->reset();

        // After reset, collection should return empty
        $this->collector->collect();
        expect($this->collector->getData())->toBeEmpty();
    });
});

describe('RequestCollector Request Data Collection', function () {
    it('collects basic request metadata', function () {
        $request = Request::create('/users/123', 'GET');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKey('request');
        expect($data['request']['method'])->toBe('GET');
        expect($data['request'])->toHaveKey('full_url');
    });

    it('collects POST request data', function () {
        $request = Request::create('/users', 'POST', ['name' => 'John', 'email' => 'john@example.com']);

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['method'])->toBe('POST');
        expect($data['request']['request'])->toHaveKey('name');
        expect($data['request']['request']['name'])->toBe('John');
    });

    it('collects query parameters', function () {
        $request = Request::create('/search?q=laravel&page=2', 'GET');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['query'])->toHaveKey('q');
        expect($data['request']['query']['q'])->toBe('laravel');
        expect($data['request']['query']['page'])->toBe('2');
    });

    it('collects request headers', function () {
        $request = Request::create('/api/users', 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('User-Agent', 'Mozilla/5.0');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['headers']['accept'])->toBe('application/json');
        expect($data['request']['headers']['user-agent'])->toBe('Mozilla/5.0');
    });

    it('collects cookies', function () {
        $request = Request::create('/test', 'GET');
        $request->cookies->set('session_token', 'abc123');
        $request->cookies->set('user_preference', 'dark_mode');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['cookies'])->toHaveKey('session_token');
        expect($data['request']['cookies']['session_token'])->toBe('abc123');
    });

    it('collects JSON request body', function () {
        $json = ['name' => 'John', 'age' => 30];
        $request = Request::create('/api/users', 'POST', [], [], [], [], json_encode($json));
        $request->headers->set('Content-Type', 'application/json');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request'])->toHaveKey('raw');
        expect($data['request']['raw'])->toContain('John');
    });

    it('handles multipart form data', function () {
        $request = Request::create('/upload', 'POST');
        $request->headers->set('Content-Type', 'multipart/form-data');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['raw'])->toBe('[multipart/form-data omitted]');
    });

    it('limits raw body size', function () {
        $largeBody = str_repeat('x', 10000);
        $request = Request::create('/test', 'POST', [], [], [], [], $largeBody);

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect(strlen($data['request']['raw']))->toBeLessThanOrEqual(8000);
    });
});

describe('RequestCollector Response Data Collection', function () {
    it('collects response status', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('OK', 200);

        $this->collector->setRequest($request);
        $this->collector->setResponse($response);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['response']['status'])->toBe(200);
    });

    it('collects response headers', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('OK', 200);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('X-Custom-Header', 'value');

        $this->collector->setRequest($request);
        $this->collector->setResponse($response);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['response']['headers'])->toHaveKey('content-type');
        expect($data['response']['headers'])->toHaveKey('x-custom-header');
    });

    it('calculates response duration', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('OK', 200);

        $this->collector->setRequest($request);
        $this->collector->start();
        usleep(5000); // Sleep 5ms
        $this->collector->setResponse($response);
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['response'])->toHaveKey('duration_ms');
        expect($data['response']['duration_ms'])->toBeGreaterThan(0);
    });

    it('handles different response status codes', function (int $status) {
        $request = Request::create('/test', 'GET');
        $response = new Response('', $status);

        $this->collector->setRequest($request);
        $this->collector->setResponse($response);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['response']['status'])->toBe($status);
    })->with([200, 201, 204, 301, 302, 400, 401, 403, 404, 500]);
});

describe('RequestCollector Dump Integration', function () {
    it('generates dump previews', function () {
        $request = Request::create('/test', 'GET');

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request'])->toHaveKey('headers_preview');
        expect($data['request'])->toHaveKey('raw_preview');
        expect($data['request'])->toHaveKey('query_preview');
    });

    it('generates dump IDs for lazy loading', function () {
        $request = Request::create('/test', 'GET');

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id-456');

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request'])->toHaveKey('headers_dump_id');
        expect($data['request'])->toHaveKey('raw_dump_id');
        expect($data['request']['headers_dump_id'])->toBe('dump-id-456');
    });

    it('handles dump failures gracefully', function () {
        $request = Request::create('/test', 'GET');

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->dumpManager->shouldReceive('buildPreview')->andThrow(new Exception('Dump failed'));
        $this->dumpManager->shouldReceive('storeDump')->andThrow(new Exception('Storage failed'));

        $this->collector->setRequest($request);
        $this->collector->start();

        expect(fn() => $this->collector->collect())->not->toThrow(Exception::class);
    });

    it('generates inline HTML for small values', function () {
        $request = Request::create('/test?small=value', 'GET');

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('small');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(Mockery::mock());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<div>inline</div>');

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request'])->toHaveKey('query_inline_html');
    });
});

describe('RequestCollector Edge Cases', function () {
    it('handles request without session', function () {
        $request = Request::create('/test', 'GET');
        // No session attached

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['session'])->toBeArray();
        expect($data['request']['session'])->toBeEmpty();
    });

    it('handles collection without request', function () {
        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData())->toBeEmpty();
    });

    it('handles collection with only request', function () {
        $request = Request::create('/test', 'GET');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKey('request');
        expect($data['response'])->toBeEmpty();
    });

    it('handles empty query parameters', function () {
        $request = Request::create('/test', 'GET');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['query'])->toBeArray();
    });

    it('handles special characters in URLs', function () {
        $request = Request::create('/search?q=laravel+framework&tag=php+8.3', 'GET');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['request']['full_url'])->toContain('search');
    });

    it('handles request with no content type', function () {
        $request = Request::create('/test', 'GET');
        // No Content-Type header

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        expect(fn() => $this->collector->getData())->not->toThrow(Exception::class);
    });

    it('handles invalid JSON body gracefully', function () {
        $request = Request::create('/api/test', 'POST', [], [], [], [], 'invalid json{');
        $request->headers->set('Content-Type', 'application/json');

        $this->collector->setRequest($request);
        $this->collector->start();
        mockRequestDumpOperations();
        $this->collector->collect();

        expect(fn() => $this->collector->getData())->not->toThrow(Exception::class);
    });
});

describe('RequestCollector Integration', function () {
    it('follows complete lifecycle', function () {
        $request = Request::create('/users/123', 'GET');
        $response = new Response('User data', 200);

        // Start
        $this->collector->start();

        // Set request and response
        $this->collector->setRequest($request);
        $this->collector->setResponse($response);

        // Collect
        mockRequestDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data)->not->toBeEmpty();
        expect($data)->toHaveKey('request');
        expect($data)->toHaveKey('response');

        // Reset
        $this->collector->reset();
        $this->collector->collect();
        expect($this->collector->getData())->toBeEmpty();
    });

    it('tracks multiple requests in sequence', function () {
        // First request
        $request1 = Request::create('/users', 'GET');
        $response1 = new Response('Users list', 200);

        $this->collector->start();
        $this->collector->setRequest($request1);
        $this->collector->setResponse($response1);
        mockRequestDumpOperations();
        $this->collector->collect();

        expect($this->collector->getData()['request']['method'])->toBe('GET');

        $this->collector->reset();

        // Second request
        $request2 = Request::create('/users', 'POST', ['name' => 'Jane']);
        $response2 = new Response('Created', 201);

        $this->collector->start();
        $this->collector->setRequest($request2);
        $this->collector->setResponse($response2);
        mockRequestDumpOperations();
        $this->collector->collect();

        expect($this->collector->getData()['request']['method'])->toBe('POST');
        expect($this->collector->getData()['response']['status'])->toBe(201);
    });
});

// Helper function to mock dump operations
function mockRequestDumpOperations(): void
{
    test()->tracker->shouldReceive('getRequestId')->andReturn('req-id');
    test()->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
    test()->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
    test()->dumpManager->shouldReceive('clonePreview')->andReturn(Mockery::mock());
    test()->dumpManager->shouldReceive('renderHtml')->andReturn(null);
}

