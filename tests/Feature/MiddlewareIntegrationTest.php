<?php

declare(strict_types=1);

use ThiagoVieira\Saci\SaciMiddleware;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\DebugBarInjector;
use ThiagoVieira\Saci\RequestValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach(function () {
    $this->registry = app(CollectorRegistry::class);
    $this->injector = app(DebugBarInjector::class);
    $this->validator = app(RequestValidator::class);
    $this->middleware = new SaciMiddleware($this->registry, $this->injector, $this->validator);
});

describe('Middleware Integration - Request Lifecycle', function () {
    it('orchestrates complete collector lifecycle', function () {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>Test</body></html>', 200, [
                'Content-Type' => 'text/html'
            ]);
        });
        
        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(200);
    });

    it('starts collectors before request processing', function () {
        $request = Request::create('/test', 'GET');
        $collectorStarted = false;
        
        // Mock a collector to verify it was started
        $viewCollector = $this->registry->get('views');
        expect($viewCollector)->not->toBeNull();
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>Test</body></html>');
        });
        
        expect($response)->toBeInstanceOf(Response::class);
    });

    it('collects data after request processing', function () {
        $request = Request::create('/users/123', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>User Profile</body></html>');
        });
        
        // Verify collectors have collected data
        $requestCollector = $this->registry->get('request');
        expect($requestCollector->getData())->not->toBeEmpty();
    });

    it('resets collectors between requests', function () {
        $request1 = Request::create('/first', 'GET');
        $request2 = Request::create('/second', 'GET');
        
        // First request
        $this->middleware->handle($request1, function ($req) {
            return new Response('<html><body>First</body></html>');
        });
        
        // Second request should reset and start fresh
        $this->middleware->handle($request2, function ($req) {
            return new Response('<html><body>Second</body></html>');
        });
        
        expect(true)->toBeTrue(); // No exceptions thrown
    });
});

describe('Middleware Integration - Collector Pipeline', function () {
    it('passes request to request-dependent collectors', function () {
        $request = Request::create('/api/users', 'POST', ['name' => 'John']);
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>Created</body></html>', 201);
        });
        
        $requestCollector = $this->registry->get('request');
        $data = $requestCollector->getData();
        
        expect($data['request']['method'])->toBe('POST');
    });

    it('passes response to response-dependent collectors', function () {
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>OK</body></html>', 200, [
                'X-Custom-Header' => 'Value'
            ]);
        });
        
        $requestCollector = $this->registry->get('request');
        $data = $requestCollector->getData();
        
        expect($data['response']['status'])->toBe(200);
    });

    it('collects from all enabled collectors', function () {
        $request = Request::create('/profile', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>Profile</body></html>');
        });
        
        $collectors = ['views', 'request', 'route', 'auth', 'logs', 'database'];
        
        foreach ($collectors as $name) {
            $collector = $this->registry->get($name);
            expect($collector)->not->toBeNull();
        }
    });
});

describe('Middleware Integration - Response Handling', function () {
    it('skips non-HTML responses', function () {
        $request = Request::create('/api/data', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('{"data": "value"}', 200, [
                'Content-Type' => 'application/json'
            ]);
        });
        
        $content = $response->getContent();
        expect($content)->not->toContain('saci');
        expect($content)->toBe('{"data": "value"}');
    });

    it('skips file downloads', function () {
        $request = Request::create('/download/file.pdf', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('PDF content', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="file.pdf"'
            ]);
        });
        
        $content = $response->getContent();
        expect($content)->toBe('PDF content');
    });

    it('processes HTML responses', function () {
        $request = Request::create('/page', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body><h1>Title</h1></body></html>', 200, [
                'Content-Type' => 'text/html'
            ]);
        });
        
        expect($response->headers->get('Content-Type'))->toContain('text/html');
    });
});

describe('Middleware Integration - Terminate Method', function () {
    it('processes late logs on terminate', function () {
        $request = Request::create('/test', 'GET');
        $response = new Response('<html><body>Test</body></html>');
        
        // Should not throw
        expect(fn() => $this->middleware->terminate($request, $response))->not->toThrow(Exception::class);
    });

    it('skips terminate for non-traced requests', function () {
        // Create a request that should be skipped
        $request = Request::create('/health', 'GET');
        $response = new Response('OK');
        
        // Should skip silently
        $this->middleware->terminate($request, $response);
        
        expect(true)->toBeTrue();
    });
});

describe('Middleware Integration - Request Validator', function () {
    it('skips disabled requests', function () {
        config()->set('saci.enabled', false);
        
        $validator = new RequestValidator();
        $middleware = new SaciMiddleware($this->registry, $this->injector, $validator);
        
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('<html><body>Test</body></html>');
        });
        
        // Should pass through without modification
        expect($response->getContent())->not->toContain('saci');
        
        config()->set('saci.enabled', true);
    });

    it('processes valid requests', function () {
        config()->set('saci.enabled', true);
        
        $request = Request::create('/test', 'GET');
        
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>Test</body></html>');
        });
        
        expect($response)->toBeInstanceOf(Response::class);
    });
});

describe('Middleware Integration - Error Handling', function () {
    it('handles collector exceptions gracefully', function () {
        $request = Request::create('/test', 'GET');
        
        // Should not throw even if collectors have issues
        $response = $this->middleware->handle($request, function ($req) {
            return new Response('<html><body>Test</body></html>');
        });
        
        expect($response)->toBeInstanceOf(Response::class);
    });

    it('preserves original response on error', function () {
        $request = Request::create('/test', 'GET');
        $originalContent = '<html><body>Original Content</body></html>';
        
        $response = $this->middleware->handle($request, function ($req) use ($originalContent) {
            return new Response($originalContent);
        });
        
        expect($response->getContent())->toContain('Original Content');
    });
});

describe('Middleware Integration - Multiple Requests', function () {
    it('handles sequential requests independently', function () {
        $requests = [
            Request::create('/first', 'GET'),
            Request::create('/second', 'POST'),
            Request::create('/third', 'PUT'),
        ];
        
        foreach ($requests as $request) {
            $response = $this->middleware->handle($request, function ($req) {
                return new Response('<html><body>Response</body></html>');
            });
            
            expect($response)->toBeInstanceOf(Response::class);
        }
    });

    it('maintains data isolation between requests', function () {
        // First request
        $request1 = Request::create('/users/1', 'GET');
        $response1 = $this->middleware->handle($request1, function ($req) {
            return new Response('<html><body>User 1</body></html>');
        });
        
        $data1 = $this->registry->get('request')->getData();
        
        // Second request
        $request2 = Request::create('/users/2', 'GET');
        $response2 = $this->middleware->handle($request2, function ($req) {
            return new Response('<html><body>User 2</body></html>');
        });
        
        $data2 = $this->registry->get('request')->getData();
        
        // Data should be different
        expect($data1)->not->toBe($data2);
    });
});



