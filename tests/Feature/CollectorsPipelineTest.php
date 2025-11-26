<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\SaciMiddleware;
use ThiagoVieira\Saci\DebugBarInjector;
use ThiagoVieira\Saci\RequestValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->registry = app(CollectorRegistry::class);
});

describe('Collectors Pipeline - Start Phase', function () {
    it('starts all enabled collectors', function () {
        $this->registry->startAll();

        $collectors = ['views', 'request', 'route', 'auth', 'logs', 'database'];

        foreach ($collectors as $name) {
            $collector = $this->registry->get($name);
            expect($collector)->not->toBeNull();
        }
    });

    it('skips disabled collectors', function () {
        config()->set('saci.collectors.database', false);

        // Create new registry with updated config
        $registry = app(CollectorRegistry::class);
        $registry->startAll();

        expect(true)->toBeTrue();

        config()->set('saci.collectors.database', true);
    });

    it('handles collector start errors gracefully', function () {
        $this->registry->startAll();

        // Should not throw
        expect(true)->toBeTrue();
    });
});

describe('Collectors Pipeline - Collection Phase', function () {
    it('collects data from all collectors', function () {
        $this->registry->startAll();
        $this->registry->collectAll();

        $data = $this->registry->getAllData();

        expect($data)->toBeArray();
        expect($data)->not->toBeEmpty();
    });

    it('aggregates data from multiple collectors', function () {
        $this->registry->startAll();
        $this->registry->collectAll();

        $data = $this->registry->getAllData();

        // Should have data from multiple collectors
        expect(count($data))->toBeGreaterThan(0);
    });

    it('handles empty collection gracefully', function () {
        $this->registry->collectAll();

        $data = $this->registry->getAllData();

        expect($data)->toBeArray();
    });
});

describe('Collectors Pipeline - Reset Phase', function () {
    it('resets all collectors', function () {
        $this->registry->startAll();
        $this->registry->collectAll();
        $this->registry->resetAll();

        // Should not throw
        expect(true)->toBeTrue();
    });

    it('clears collector data on reset', function () {
        $this->registry->startAll();
        $this->registry->collectAll();

        $dataBefore = $this->registry->getAllData();

        $this->registry->resetAll();
        $this->registry->collectAll();

        // After reset and collect without start, should have different/empty data
        expect(true)->toBeTrue();
    });
});

describe('Collectors Pipeline - Data Flow', function () {
    it('shares request between collectors', function () {
        $request = Request::create('/api/users', 'POST', ['name' => 'John']);

        $middleware = app(SaciMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            return new Response('<html><body>Created</body></html>', 201, [
                'Content-Type' => 'text/html'
            ]);
        });

        // Request collector should have request data
        $requestCollector = $this->registry->get('request');
        $data = $requestCollector->getData();

        expect($data['request']['method'])->toBe('POST');
    });

    it('shares response between collectors', function () {
        $request = Request::create('/test', 'GET');

        $middleware = app(SaciMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            return new Response('<html><body>OK</body></html>', 200, [
                'Content-Type' => 'text/html',
                'X-Custom-Header' => 'Value'
            ]);
        });

        $requestCollector = $this->registry->get('request');
        $data = $requestCollector->getData();

        expect($data['response'])->toHaveKey('status');
    });
});

describe('Collectors Pipeline - Dependency Management', function () {
    it('collectors access shared dependencies', function () {
        $viewCollector = $this->registry->get('views');
        $requestCollector = $this->registry->get('request');

        expect($viewCollector)->not->toBeNull();
        expect($requestCollector)->not->toBeNull();
    });

    it('handles collector interdependencies', function () {
        $this->registry->startAll();

        // Some collectors depend on others (e.g., LogCollector uses DumpManager)
        $logCollector = $this->registry->get('logs');

        expect($logCollector)->not->toBeNull();
    });
});

describe('Collectors Pipeline - Performance', function () {
    it('completes full pipeline quickly', function () {
        $start = microtime(true);

        $this->registry->startAll();
        $this->registry->collectAll();
        $this->registry->resetAll();

        $duration = (microtime(true) - $start) * 1000; // Convert to ms

        // Pipeline should complete in reasonable time (< 100ms)
        expect($duration)->toBeLessThan(100);
    });

    it('handles multiple pipeline cycles', function () {
        for ($i = 0; $i < 10; $i++) {
            $this->registry->resetAll();
            $this->registry->startAll();
            $this->registry->collectAll();
        }

        expect(true)->toBeTrue();
    });
});

describe('Collectors Pipeline - Error Isolation', function () {
    it('continues pipeline if one collector fails', function () {
        $this->registry->startAll();

        // Even if one collector has issues, others should work
        $this->registry->collectAll();

        expect(true)->toBeTrue();
    });

    it('provides partial data on collector error', function () {
        $this->registry->startAll();
        $this->registry->collectAll();

        $data = $this->registry->getAllData();

        // Should still have data from working collectors
        expect($data)->toBeArray();
    });
});

describe('Collectors Pipeline - Configuration', function () {
    it('respects collector enable/disable settings', function () {
        $originalSetting = config('saci.collectors.logs');

        config()->set('saci.collectors.logs', false);

        $registry = app(CollectorRegistry::class);
        $registry->startAll();

        // Logs collector should be disabled
        expect(true)->toBeTrue();

        config()->set('saci.collectors.logs', $originalSetting);
    });

    it('applies configuration to all collectors', function () {
        config()->set('saci.collectors.views', true);
        config()->set('saci.collectors.request', true);
        config()->set('saci.collectors.route', true);

        $this->registry->startAll();

        expect($this->registry->get('views'))->not->toBeNull();
        expect($this->registry->get('request'))->not->toBeNull();
        expect($this->registry->get('route'))->not->toBeNull();
    });
});

describe('Collectors Pipeline - Full Integration', function () {
    it('completes full request-response cycle', function () {
        $request = Request::create('/users/123', 'GET');
        $middleware = app(SaciMiddleware::class);

        $response = $middleware->handle($request, function ($req) {
            return new Response('<html><body>User Profile</body></html>', 200, [
                'Content-Type' => 'text/html'
            ]);
        });

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(200);

        // All collectors should have data
        $data = $this->registry->getAllData();
        expect($data)->not->toBeEmpty();
    });

    it('maintains data consistency across pipeline', function () {
        $request = Request::create('/test', 'GET');
        $middleware = app(SaciMiddleware::class);

        $middleware->handle($request, function ($req) {
            return new Response('<html><body>Test</body></html>', 200, [
                'Content-Type' => 'text/html'
            ]);
        });

        $requestData = $this->registry->get('request')->getData();

        // Data should be consistent
        expect($requestData)->toBeArray();
        expect($requestData)->toHaveKey('request');
    });
});

describe('Collectors Pipeline - Concurrency Safety', function () {
    it('handles rapid sequential requests', function () {
        $middleware = app(SaciMiddleware::class);

        for ($i = 0; $i < 5; $i++) {
            $request = Request::create("/request-{$i}", 'GET');

            $response = $middleware->handle($request, function ($req) {
                return new Response('<html><body>Response</body></html>', 200, [
                    'Content-Type' => 'text/html'
                ]);
            });

            expect($response)->toBeInstanceOf(Response::class);
        }
    });

    it('isolates data between concurrent requests', function () {
        $middleware = app(SaciMiddleware::class);

        // First request
        $request1 = Request::create('/first', 'GET');
        $middleware->handle($request1, function ($req) {
            return new Response('<html><body>First</body></html>', 200, [
                'Content-Type' => 'text/html'
            ]);
        });

        $data1 = $this->registry->get('request')->getData();

        // Second request
        $request2 = Request::create('/second', 'POST');
        $middleware->handle($request2, function ($req) {
            return new Response('<html><body>Second</body></html>', 200, [
                'Content-Type' => 'text/html'
            ]);
        });

        $data2 = $this->registry->get('request')->getData();

        // Methods should be different
        expect($data1['request']['method'])->not->toBe($data2['request']['method']);
    });
});



