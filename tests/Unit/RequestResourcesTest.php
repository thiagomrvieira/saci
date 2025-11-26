<?php

namespace ThiagoVieira\Saci\Tests\Unit;

use ThiagoVieira\Saci\RequestResources;
use ThiagoVieira\Saci\TemplateTracker;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\LogCollector;
use ThiagoVieira\Saci\Support\LogProcessor;
use ThiagoVieira\Saci\Support\LateLogsPersistence;
use ThiagoVieira\Saci\Support\FilePathResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Mockery;

beforeEach(function () {
    $this->dumpManager = Mockery::mock(DumpManager::class);
    $this->tracker = Mockery::mock(TemplateTracker::class);
    $this->logCollector = Mockery::mock(LogCollector::class);
    $this->logProcessor = Mockery::mock(LogProcessor::class);
    $this->lateLogsPersistence = Mockery::mock(LateLogsPersistence::class);
    $this->pathResolver = Mockery::mock(FilePathResolver::class);

    // Default expectations
    $this->tracker->shouldReceive('getRequestId')->andReturn('req-123')->byDefault();
    $this->logCollector->shouldReceive('getRawLogs')->andReturn([])->byDefault();
    $this->logCollector->shouldReceive('start')->byDefault();
    $this->logProcessor->shouldReceive('process')->andReturn([])->byDefault();

    $this->resources = new RequestResources(
        $this->dumpManager,
        $this->tracker,
        $this->logCollector,
        $this->logProcessor,
        $this->lateLogsPersistence,
        $this->pathResolver
    );
});

afterEach(function () {
    Mockery::close();
});

// ============================================================================
// 1. INITIALIZATION & START
// ============================================================================

describe('Initialization & Start', function () {
    it('initializes with empty state', function () {
        $data = $this->resources->getData();

        expect($data)->toHaveKeys(['route', 'request', 'response', 'auth', 'logs']);
        expect($data['route'])->toBeEmpty();
        expect($data['request'])->toBeEmpty();
        expect($data['response'])->toBeEmpty();
        expect($data['auth'])->toBeEmpty();
        expect($data['logs'])->toBeEmpty();
    });

    it('starts log collection on start', function () {
        $this->logCollector->shouldReceive('start')->once();

        $this->resources->start();
    });

    it('resets state on start', function () {
        // Collect some data first
        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');

        // Mock dump operations
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->pathResolver->shouldReceive('toRelative')->andReturn('/test');

        $this->resources->collectFromRequest($request);

        expect($this->resources->getData()['route'])->not->toBeEmpty();

        // Start again - should reset
        $this->resources->start();

        expect($this->resources->getData()['route'])->toBeEmpty();
    });

    it('records start time on start', function () {
        $this->resources->start();

        // Collect response to calculate duration
        $response = new Response('test');
        $this->resources->collectFromResponse($response);

        $data = $this->resources->getData();

        expect($data['response'])->toHaveKey('duration_ms');
        expect($data['response']['duration_ms'])->toBeGreaterThanOrEqual(0);
    });
});

// ============================================================================
// 2. ROUTE COLLECTION
// ============================================================================

describe('Route Collection', function () {
    it('collects basic route information', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        $route->name('test.route');

        // Mock expectations
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['route'])->toHaveKeys(['name', 'uri', 'methods', 'action']);
        expect($data['route']['name'])->toBe('test.route');
        expect($data['route']['uri'])->toBe('test');
        expect($data['route']['methods'])->toContain('GET');
    });

    it('handles routes without route object gracefully', function () {
        $request = Request::create('/test', 'GET');
        // No route set

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['route'])->toBeEmpty();
    });

    it('collects route with parameters', function () {
        [$route, $request] = createBoundRoute(
            ['GET'],
            '/users/{id}',
            fn($id) => "User $id",
            ['id' => '123']
        );

        $route->name('users.show');

        // Mock expectations
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['route']['parameters'])->toBe(['id' => '123']);
    });

    it('collects route middleware', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        $route->middleware(['auth', 'verified']);

        // Mock expectations
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['route']['middleware'])->toContain('auth', 'verified');
    });

    it('collects controller information from class-based routes', function () {
        // Create a test controller class
        $controller = new class {
            public function index() { return 'test'; }
        };

        [$route, $request] = createSimpleRoute(['GET'], '/test', [$controller::class, 'index']);

        // Mock expectations
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->pathResolver->shouldReceive('toRelative')->andReturn('/test/Controller.php');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['route'])->toHaveKey('controller');
        expect($data['route'])->toHaveKey('controller_method');
        expect($data['route'])->toHaveKey('controller_file');
    });

    it('handles closure routes', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', function () {
            return 'test closure';
        });

        // Mock expectations
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
        $this->pathResolver->shouldReceive('toRelative')->andReturn('/routes/web.php');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['route']['action'])->toBe('Closure');
        expect($data['route']['controller_file'])->toBe('/routes/web.php');
    });
});

// ============================================================================
// 3. REQUEST COLLECTION
// ============================================================================

describe('Request Collection', function () {
    beforeEach(function () {
        // Common dump expectations
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());
    });

    it('collects request method and URL', function () {
        [$route, $request] = createSimpleRoute(['POST'], '/test', fn() => 'test');
        // Override request URL to include full URL and query string
        $request = Request::create('https://example.com/test?foo=bar', 'POST');
        $request->setRouteResolver(fn() => $route);

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['request']['method'])->toBe('POST');
        expect($data['request']['full_url'])->toContain('example.com/test?foo=bar');
    });

    it('collects query parameters', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        // Add query parameters to the request
        $request->query->add(['name' => 'John', 'age' => '30']);

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['request']['query'])->toBe(['name' => 'John', 'age' => '30']);
    });

    it('collects request headers', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        // Add custom headers
        $request->headers->set('User-Agent', 'Mozilla/5.0');
        $request->headers->set('Accept', 'text/html');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['request']['headers']['user-agent'])->toBe('Mozilla/5.0');
        expect($data['request']['headers']['accept'])->toBe('text/html');
    });

    it('collects cookies', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        // Add cookies
        $request->cookies->set('session_id', 'abc123');
        $request->cookies->set('theme', 'dark');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['request']['cookies'])->toHaveKey('session_id');
        expect($data['request']['cookies']['session_id'])->toBe('abc123');
    });

    it('collects JSON request body', function () {
        [$route, $request] = createSimpleRoute(['POST'], '/test', fn() => 'test');
        // Add JSON body
        $json = json_encode(['name' => 'John', 'email' => 'john@example.com']);
        $request->headers->set('Content-Type', 'application/json');
        $request->initialize([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $json);
        $request->setRouteResolver(fn() => $route);

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        // Should pretty-print JSON
        expect($data['request']['raw'])->toContain('"name"');
        expect($data['request']['raw'])->toContain('"John"');
    });

    it('omits multipart form data body', function () {
        [$route, $request] = createSimpleRoute(['POST'], '/test', fn() => 'test');
        // Set multipart content type
        $request->headers->set('Content-Type', 'multipart/form-data; boundary=----WebKitFormBoundary');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['request']['raw'])->toBe('[multipart/form-data omitted]');
    });

    it('limits raw body size to 8000 characters', function () {
        [$route, $request] = createSimpleRoute(['POST'], '/test', fn() => 'test');
        // Add large body
        $largeBody = str_repeat('a', 10000);
        $request->initialize([], [], [], [], [], [], $largeBody);
        $request->setRouteResolver(fn() => $route);

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect(mb_strlen($data['request']['raw']))->toBeLessThanOrEqual(8000);
    });
});

// ============================================================================
// 4. AUTH COLLECTION
// ============================================================================

describe('Auth Collection', function () {
    beforeEach(function () {
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());

        config(['auth.defaults.guard' => 'web']);
    });

    it('collects guest user information', function () {
        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null);

        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['auth']['authenticated'])->toBeFalse();
        expect($data['auth']['guard'])->toBe('web');
        expect($data['auth']['id'])->toBeNull();
    });

    it('collects authenticated user information', function () {
        $user = new class {
            public $email = 'john@example.com';
            public $name = 'John Doe';

            public function getAuthIdentifier() {
                return 123;
            }
        };

        Auth::shouldReceive('guard')->with('web')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn($user);

        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['auth']['authenticated'])->toBeTrue();
        expect($data['auth']['id'])->toBe(123);
        expect($data['auth']['email'])->toBe('john@example.com');
        expect($data['auth']['name'])->toBe('John Doe');
    });
});

// ============================================================================
// 5. RESPONSE COLLECTION
// ============================================================================

describe('Response Collection', function () {
    it('collects response status and headers', function () {
        $this->logProcessor->shouldReceive('process')->andReturn([]);

        $this->resources->start();

        $response = new Response('test content', 200, [
            'Content-Type' => 'text/html',
            'X-Custom-Header' => 'custom-value',
        ]);

        $this->resources->collectFromResponse($response);

        $data = $this->resources->getData();

        expect($data['response']['status'])->toBe(200);
        expect($data['response']['content_type'])->toBe('text/html');
        expect($data['response']['headers'])->toHaveKey('x-custom-header');
    });

    it('calculates request duration', function () {
        $this->logProcessor->shouldReceive('process')->andReturn([]);

        $this->resources->start();
        usleep(10000); // 10ms delay

        $response = new Response('test');
        $this->resources->collectFromResponse($response);

        $data = $this->resources->getData();

        expect($data['response']['duration_ms'])->toBeGreaterThan(5);
        expect($data['response']['duration_ms'])->toBeLessThan(100);
    });

    it('handles missing start time gracefully', function () {
        $this->logProcessor->shouldReceive('process')->andReturn([]);

        // Don't call start()
        $response = new Response('test');
        $this->resources->collectFromResponse($response);

        $data = $this->resources->getData();

        expect($data['response']['duration_ms'])->toBeNull();
    });
});

// ============================================================================
// 6. LOGS COLLECTION
// ============================================================================

describe('Logs Collection', function () {
    it('processes initial logs on response collection', function () {
        $rawLogs = [
            ['level' => 'info', 'message' => 'Test log'],
        ];

        $this->logCollector->shouldReceive('getRawLogs')->andReturn($rawLogs);
        $this->logProcessor->shouldReceive('process')
            ->once()
            ->with($rawLogs, 'req-123')
            ->andReturn([
                ['level' => 'info', 'message' => 'Test log', 'timestamp' => time()],
            ]);

        $response = new Response('test');
        $this->resources->collectFromResponse($response);

        $data = $this->resources->getData();

        expect($data['logs'])->toHaveCount(1);
        expect($data['logs'][0]['message'])->toBe('Test log');
    });

    it('processes late logs after response', function () {
        // Initial logs
        $this->logCollector->shouldReceive('getRawLogs')
            ->andReturn(
                [['level' => 'info', 'message' => 'Initial log']],
                [
                    ['level' => 'info', 'message' => 'Initial log'],
                    ['level' => 'debug', 'message' => 'Late log'],
                ]
            );

        $this->logProcessor->shouldReceive('process')
            ->with([['level' => 'info', 'message' => 'Initial log']], 'req-123')
            ->once()
            ->andReturn([['level' => 'info', 'message' => 'Initial log', 'timestamp' => time()]]);

        $this->logProcessor->shouldReceive('process')
            ->with([
                ['level' => 'info', 'message' => 'Initial log'],
                ['level' => 'debug', 'message' => 'Late log'],
            ], 'req-123', 1)
            ->once()
            ->andReturn([['level' => 'debug', 'message' => 'Late log', 'timestamp' => time()]]);

        $this->lateLogsPersistence->shouldReceive('persist')
            ->once()
            ->with('req-123', Mockery::type('array'));

        // Collect initial
        $response = new Response('test');
        $this->resources->collectFromResponse($response);

        // Process late logs
        $this->resources->processLateLogsIfNeeded();

        $data = $this->resources->getData();

        expect($data['logs'])->toHaveCount(2);
    });

    it('skips late log processing if no new logs', function () {
        $this->logCollector->shouldReceive('getRawLogs')
            ->andReturn([['level' => 'info', 'message' => 'Log']]);

        $this->logProcessor->shouldReceive('process')
            ->once()
            ->andReturn([['level' => 'info', 'message' => 'Log']]);

        $this->lateLogsPersistence->shouldReceive('persist')->never();

        $response = new Response('test');
        $this->resources->collectFromResponse($response);

        // Try to process late logs (but there are none)
        $this->resources->processLateLogsIfNeeded();
    });
});

// ============================================================================
// 7. DUMP GENERATION
// ============================================================================

describe('Dump Generation', function () {
    it('generates previews for request data', function () {
        $this->dumpManager->shouldReceive('buildPreview')
            ->andReturn('test-preview');

        $this->dumpManager->shouldReceive('storeDump')
            ->andReturn('dump-123');

        $this->dumpManager->shouldReceive('renderHtml')->andReturn('<html>');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());

        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        $request->query->add(['foo' => 'bar']);

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['request'])->toHaveKey('headers_preview');
        expect($data['request'])->toHaveKey('query_preview');
        expect($data['request'])->toHaveKey('cookies_preview');
    });

    it('generates inline HTML for small values', function () {
        $this->dumpManager->shouldReceive('buildPreview')
            ->with(['key' => 'value'])
            ->andReturn('small');

        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $this->dumpManager->shouldReceive('clonePreview')
            ->with(['key' => 'value'])
            ->andReturn(new \stdClass());

        $this->dumpManager->shouldReceive('renderHtml')
            ->with(Mockery::type('object'))
            ->andReturn('<span class="dump">small</span>');

        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        $request->query->set('key', 'value');

        // Need many more buildPreview calls for other data
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');

        $this->resources->collectFromRequest($request);

        $data = $this->resources->getData();

        expect($data['request'])->toHaveKey('query_inline_html');
    });
});

// ============================================================================
// 8. GET DATA
// ============================================================================

describe('Get Data', function () {
    it('returns complete data structure', function () {
        $data = $this->resources->getData();

        expect($data)->toHaveKeys(['route', 'request', 'response', 'auth', 'logs']);
    });

    it('returns aggregated data from all sources', function () {
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(new \stdClass());

        config(['auth.defaults.guard' => 'web']);
        Auth::shouldReceive('guard')->andReturnSelf();
        Auth::shouldReceive('user')->andReturn(null);

        $this->logProcessor->shouldReceive('process')->andReturn([
            ['level' => 'info', 'message' => 'Test'],
        ]);

        $this->resources->start();

        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        $route->name('test.route');

        $this->resources->collectFromRequest($request);

        $response = new Response('test');
        $this->resources->collectFromResponse($response);

        $data = $this->resources->getData();

        expect($data['route'])->not->toBeEmpty();
        expect($data['request'])->not->toBeEmpty();
        expect($data['response'])->not->toBeEmpty();
        expect($data['auth'])->not->toBeEmpty();
        expect($data['logs'])->toHaveCount(1);
    });
});

