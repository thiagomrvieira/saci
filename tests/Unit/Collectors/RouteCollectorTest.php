<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Collectors\RouteCollector;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\FilePathResolver;
use ThiagoVieira\Saci\TemplateTracker;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

beforeEach(function () {
    $this->dumpManager = Mockery::mock(DumpManager::class);
    $this->pathResolver = Mockery::mock(FilePathResolver::class);
    $this->tracker = Mockery::mock(TemplateTracker::class);
    $this->collector = new RouteCollector($this->dumpManager, $this->pathResolver, $this->tracker);
});

afterEach(function () {
    Mockery::close();
});

describe('RouteCollector Identity', function () {
    it('returns correct name', function () {
        expect($this->collector->getName())->toBe('route');
    });

    it('returns correct label', function () {
        expect($this->collector->getLabel())->toBe('Route');
    });

    it('is enabled by default', function () {
        config()->set('saci.collectors.route', true);
        expect($this->collector->isEnabled())->toBeTrue();
    });
});

describe('RouteCollector Lifecycle', function () {
    it('accepts request via setRequest', function () {
        $request = Request::create('/test', 'GET');

        expect(fn() => $this->collector->setRequest($request))->not->toThrow(Exception::class);
    });

    it('resets state on reset', function () {
        $request = Request::create('/test', 'GET');

        $this->collector->setRequest($request);
        $this->collector->reset();

        // After reset, should not have request
        $this->collector->collect();
        expect($this->collector->getData())->toBeEmpty();
    });
});

describe('RouteCollector Basic Route Data', function () {
    it('collects route name', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/users', function () {});
        $route->name('users.index');

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['name'])->toBe('users.index');
    });

    it('collects route URI', function () {
        [$route, $request] = createBoundRoute(['GET'], '/users/{id}', function () {}, ['id' => '123']);

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['uri'])->toBe('users/{id}');
    });

    it('collects HTTP methods', function () {
        [$route, $request] = createSimpleRoute(['GET', 'POST'], '/api/resource', function () {});

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['methods'])->toContain('GET');
        expect($data['methods'])->toContain('POST');
    });

    it('collects route parameters', function () {
        [$route, $request] = createBoundRoute(
            ['GET'],
            '/users/{id}/posts/{post}',
            function () {},
            ['id' => '123', 'post' => '456']
        );

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['parameters'])->toHaveKey('id');
        expect($data['parameters'])->toHaveKey('post');
    });

    it('collects middleware stack', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/admin', function () {});
        $route->middleware(['auth', 'admin']);

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['middleware'])->toContain('auth');
        expect($data['middleware'])->toContain('admin');
    });
});

describe('RouteCollector Controller Information', function () {
    it('handles closure routes', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/closure', function () { return 'test'; });

        mockRouteDumpOperations();
        $this->pathResolver->shouldReceive('toRelative')->andReturn('routes/web.php');

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['action'])->toBe('Closure');
        expect($data['controller'])->toBeNull();
    });

    it('handles named routes without controller', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', function () {});
        $route->name('test.route');

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['name'])->toBe('test.route');
        expect($data['action'])->toBe('Closure');
    });
});

describe('RouteCollector Route Constraints', function () {
    it('collects where clauses', function () {
        [$route, $request] = createBoundRoute(
            ['GET'],
            '/users/{id}',
            function () {},
            ['id' => '123'],
            ['id' => '[0-9]+']
        );

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['where'])->toHaveKey('id');
        expect($data['where']['id'])->toBe('[0-9]+');
    });

    it('handles multiple constraints', function () {
        [$route, $request] = createBoundRoute(
            ['GET'],
            '/posts/{year}/{month}',
            function () {},
            ['year' => '2024', 'month' => '11'],
            ['year' => '[0-9]{4}', 'month' => '[0-9]{2}']
        );

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['where'])->toHaveKey('year');
        expect($data['where'])->toHaveKey('month');
    });
});

describe('RouteCollector Dump Integration', function () {
    it('generates middleware dump preview', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', function () {});
        $route->middleware(['auth', 'verified']);

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('middleware preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(Mockery::mock());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKey('middleware_preview');
        expect($data)->toHaveKey('middleware_dump_id');
    });

    it('generates parameters dump preview', function () {
        [$route, $request] = createBoundRoute(['GET'], '/users/{id}', function () {}, ['id' => '123']);

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->dumpManager->shouldReceive('buildPreview')->andReturn('params preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
        $this->dumpManager->shouldReceive('clonePreview')->andReturn(Mockery::mock());
        $this->dumpManager->shouldReceive('renderHtml')->andReturn(null);

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKey('parameters_preview');
        expect($data)->toHaveKey('parameters_dump_id');
    });

    it('handles dump failures gracefully', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', function () {});

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        $this->dumpManager->shouldReceive('buildPreview')->andThrow(new Exception('Dump failed'));
        $this->dumpManager->shouldReceive('storeDump')->andThrow(new Exception('Storage failed'));

        $this->collector->setRequest($request);
        $this->collector->start();

        expect(fn() => $this->collector->collect())->not->toThrow(Exception::class);
    });
});

describe('RouteCollector Edge Cases', function () {
    it('handles request without route', function () {
        $request = Request::create('/test', 'GET');
        // No route set

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData())->toBeEmpty();
    });

    it('handles collection without request', function () {
        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData())->toBeEmpty();
    });

    it('handles route without name', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/unnamed', function () {});

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['name'])->toBeNull();
        expect($data['uri'])->not->toBeNull();
    });

    it('handles route without middleware', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/public', function () {});

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['middleware'])->toBeArray();
    });

    it('handles route without parameters', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/static', function () {});

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['parameters'])->toBeArray();
        expect($data['parameters'])->toBeEmpty();
    });

    it('handles route without constraints', function () {
        [$route, $request] = createBoundRoute(['GET'], '/any/{param}', function () {}, ['param' => 'value']);

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data['where'])->toBeArray();
        expect($data['where'])->toBeEmpty();
    });

    it('handles routes with special characters', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/api/v1/üñíçödé', function () {});

        mockRouteDumpOperations();
        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        expect(fn() => $this->collector->getData())->not->toThrow(Exception::class);
    });
});

describe('RouteCollector Integration', function () {
    it('follows complete lifecycle', function () {
        $route = new Route(['GET'], '/users/{id}', function () {});
        $route->name('users.show');
        $route->middleware(['auth']);
        $route->where('id', '[0-9]+');

        $route->bind($request = Request::create('/users/123', 'GET'));
        $request->setRouteResolver(fn() => $route);

        // Start
        $this->collector->start();

        // Set request
        $this->collector->setRequest($request);

        // Collect
        mockRouteDumpOperations();
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data)->not->toBeEmpty();
        expect($data['name'])->toBe('users.show');
        expect($data['middleware'])->toContain('auth');

        // Reset
        $this->collector->reset();
        $this->collector->collect();
        expect($this->collector->getData())->toBeEmpty();
    });

    it('skips dumps when tracker has no request ID', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', function () {});

        // Mock tracker that returns null for getRequestId
        $tracker = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();
        $tracker->shouldReceive('getRequestId')->andReturn(null);
        $collector = new RouteCollector($this->dumpManager, $this->pathResolver, $tracker);

        $collector->setRequest($request);
        $collector->start();
        $collector->collect();

        $data = $collector->getData();
        // Should not have preview/dump keys
        expect($data)->not->toHaveKey('middleware_preview');
        expect($data)->not->toHaveKey('parameters_preview');
        expect($data)->not->toHaveKey('where_preview');
    });

    it('returns null for inline HTML when value is large', function () {
        [$route, $request] = createSimpleRoute(['GET'], '/test', function () {});
        $route->middleware(['web', 'auth', 'verified']); // Larger value

        $this->tracker->shouldReceive('getRequestId')->andReturn('req-123');
        // Large preview (>120 chars)
        $this->dumpManager->shouldReceive('buildPreview')->andReturn(str_repeat('x', 121));
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data['middleware_inline_html'])->toBeNull();
    });
});

describe('Controller and Closure Reflection Edge Cases', function () {
    it('handles invokable controller without method', function () {
        // Invokable controller (no @method, uses __invoke)
        $invokableController = new class {
            public function __invoke() {
                return 'invoked';
            }
        };

        // Create route with invokable controller
        $controllerClass = get_class($invokableController);
        $route = new Route(['GET'], '/invokable', ['uses' => $controllerClass]);

        // Manually set the action property to simulate Laravel's routing
        $action = $route->getAction();
        $action['controller'] = $controllerClass;
        $route->setAction($action);

        $request = Request::create('/invokable', 'GET');
        $route->bind($request);
        $request->setRouteResolver(fn() => $route);

        $this->pathResolver->shouldReceive('toRelative')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn('app/Http/Controllers/InvokableController.php');

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data)->toHaveKey('controller');
        expect($data['controller_method'])->toBeNull();
        expect($data['controller_file'])->toBe('app/Http/Controllers/InvokableController.php');
    })->skip('Needs investigation for anonymous class handling');

    it('handles controller reflection exception gracefully', function () {
        // Create a route with a non-existent controller class
        $route = new Route(['GET'], '/broken', ['uses' => 'NonExistentController@index']);
        $request = Request::create('/broken', 'GET');
        $route->bind($request);
        $request->setRouteResolver(fn() => $route);

        // No pathResolver expectation since class doesn't exist

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();
        // Controller might be null if class doesn't exist
        expect($data)->toHaveKey('controller');
        expect($data)->toHaveKey('controller_method');
        expect($data['controller_file'])->toBeNull();
    });

    it('handles closure reflection exception gracefully', function () {
        // Create a route with a closure that throws during reflection
        $route = new Route(['GET'], '/closure-error', function () {
            return 'test';
        });
        $request = Request::create('/closure-error', 'GET');
        $route->bind($request);
        $request->setRouteResolver(fn() => $route);

        // Mock pathResolver to throw exception during reflection
        $this->pathResolver->shouldReceive('toRelative')
            ->andThrow(new \RuntimeException('Reflection error'));

        $this->collector->setRequest($request);
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();
        expect($data['controller'])->toBeNull();
        expect($data['controller_method'])->toBeNull();
        expect($data['controller_file'])->toBeNull();
    });
});

describe('RouteCollector Coverage Improvements', function () {
    it('skips dump generation when tracker returns null requestId', function () {
        // Mock tracker to return null for getRequestId (line 142)
        $trackerNoId = Mockery::mock(TemplateTracker::class);
        $trackerNoId->shouldReceive('getRequestId')->andReturn(null);
        
        $collector = new RouteCollector(
            $this->dumpManager,
            $this->pathResolver,
            $trackerNoId
        );
        
        [$route, $request] = createSimpleRoute(['GET'], '/test', fn() => 'test');
        
        // DumpManager should NOT receive any calls since we return early at line 142
        $this->dumpManager->shouldReceive('buildPreview')->never();
        $this->dumpManager->shouldReceive('storeDump')->never();
        
        $collector->setRequest($request);
        $collector->start();
        $collector->collect();
        
        $data = $collector->getData();
        
        // Should have basic data but no dump IDs/previews
        expect($data)->toHaveKey('uri');
        expect($data['uri'])->toBe('test');
    });
});

// Helper function
function mockRouteDumpOperations(): void
{
    test()->tracker->shouldReceive('getRequestId')->andReturn('req-id');
    test()->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
    test()->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
    test()->dumpManager->shouldReceive('clonePreview')->andReturn(Mockery::mock());
    test()->dumpManager->shouldReceive('renderHtml')->andReturn(null);
}

