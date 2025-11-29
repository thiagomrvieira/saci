<?php

declare(strict_types=1);

use ThiagoVieira\Saci\RequestResources;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\Collectors\RequestCollector;
use ThiagoVieira\Saci\Collectors\RouteCollector;
use ThiagoVieira\Saci\Collectors\AuthCollector;
use ThiagoVieira\Saci\Collectors\LogCollector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $this->registry = Mockery::mock(CollectorRegistry::class);
    $this->requestResources = new RequestResources($this->registry);
});

describe('RequestResources Lifecycle', function () {
    it('starts all collectors via registry', function () {
        $this->registry->shouldReceive('startAll')->once();

        $this->requestResources->start();
    });

    it('sets request on request collector', function () {
        $request = Request::create('/test', 'GET');
        $collector = Mockery::mock(RequestCollector::class);
        $collector->shouldReceive('setRequest')->once()->with($request);

        $this->registry->shouldReceive('get')->with('request')->andReturn($collector);
        $this->registry->shouldReceive('get')->with('route')->andReturn(null);
        $this->registry->shouldReceive('get')->with('auth')->andReturn(null);

        $this->requestResources->collectFromRequest($request);
    });

    it('sets request on route collector', function () {
        $request = Request::create('/test', 'GET');
        $collector = Mockery::mock(RouteCollector::class);
        $collector->shouldReceive('setRequest')->once()->with($request);

        $this->registry->shouldReceive('get')->with('request')->andReturn(null);
        $this->registry->shouldReceive('get')->with('route')->andReturn($collector);
        $this->registry->shouldReceive('get')->with('auth')->andReturn(null);

        $this->requestResources->collectFromRequest($request);
    });

    it('sets request on auth collector', function () {
        $request = Request::create('/test', 'GET');
        $collector = Mockery::mock(AuthCollector::class);
        $collector->shouldReceive('setRequest')->once()->with($request);

        $this->registry->shouldReceive('get')->with('request')->andReturn(null);
        $this->registry->shouldReceive('get')->with('route')->andReturn(null);
        $this->registry->shouldReceive('get')->with('auth')->andReturn($collector);

        $this->requestResources->collectFromRequest($request);
    });

    it('sets response on request collector and triggers collection', function () {
        $response = new Response('test');
        $collector = Mockery::mock(RequestCollector::class);
        $collector->shouldReceive('setResponse')->once()->with($response);

        $this->registry->shouldReceive('get')->with('request')->andReturn($collector);
        $this->registry->shouldReceive('collectAll')->once();

        $this->requestResources->collectFromResponse($response);
    });

    it('processes late logs via log collector', function () {
        $collector = Mockery::mock(LogCollector::class);
        $collector->shouldReceive('processLateLogs')->once();

        $this->registry->shouldReceive('get')->with('logs')->andReturn($collector);

        $this->requestResources->processLateLogsIfNeeded();
    });
});

describe('RequestResources Data Collection', function () {
    it('collects data from all collectors', function () {
        $requestCollector = Mockery::mock(RequestCollector::class);
        $routeCollector = Mockery::mock(RouteCollector::class);
        $authCollector = Mockery::mock(AuthCollector::class);
        $logCollector = Mockery::mock(LogCollector::class);

        $requestCollector->shouldReceive('getData')->andReturn([
            'request' => ['method' => 'GET'],
            'response' => ['status' => 200],
        ]);
        $routeCollector->shouldReceive('getData')->andReturn(['name' => 'test.route']);
        $authCollector->shouldReceive('getData')->andReturn(['user' => 'test@example.com']);
        $logCollector->shouldReceive('getData')->andReturn(['logs' => [['level' => 'info']]]);

        $this->registry->shouldReceive('get')->with('request')->andReturn($requestCollector);
        $this->registry->shouldReceive('get')->with('route')->andReturn($routeCollector);
        $this->registry->shouldReceive('get')->with('auth')->andReturn($authCollector);
        $this->registry->shouldReceive('get')->with('logs')->andReturn($logCollector);

        $data = $this->requestResources->getData();

        expect($data)->toHaveKey('route');
        expect($data)->toHaveKey('request');
        expect($data)->toHaveKey('response');
        expect($data)->toHaveKey('auth');
        expect($data)->toHaveKey('logs');
        expect($data['request'])->toBe(['method' => 'GET']);
        expect($data['response'])->toBe(['status' => 200]);
    });

    it('handles missing collectors gracefully', function () {
        $this->registry->shouldReceive('get')->andReturn(null);

        $data = $this->requestResources->getData();

        expect($data)->toBeArray();
        expect($data['route'])->toBe([]);
        expect($data['request'])->toBe([]);
        expect($data['response'])->toBe([]);
        expect($data['auth'])->toBe([]);
        expect($data['logs'])->toBe([]);
    });

    it('provides toArray alias for getData', function () {
        $this->registry->shouldReceive('get')->andReturn(null);

        $data = $this->requestResources->toArray();

        expect($data)->toBeArray();
        expect($data)->toHaveKeys(['route', 'request', 'response', 'auth', 'logs']);
    });
});

describe('RequestResources Edge Cases', function () {
    it('handles null collectors in collectFromRequest', function () {
        $this->registry->shouldReceive('get')->andReturn(null);

        $request = Request::create('/test', 'GET');

        // Should not throw, just skip the collectors
        expect(fn() => $this->requestResources->collectFromRequest($request))->not->toThrow(\Exception::class);
    });

    it('handles null collectors in collectFromResponse', function () {
        $this->registry->shouldReceive('get')->with('request')->andReturn(null);
        $this->registry->shouldReceive('collectAll')->once();

        $response = new Response('test');

        expect(fn() => $this->requestResources->collectFromResponse($response))->not->toThrow(\Exception::class);
    });

    it('handles null collector in processLateLogsIfNeeded', function () {
        $this->registry->shouldReceive('get')->with('logs')->andReturn(null);

        expect(fn() => $this->requestResources->processLateLogsIfNeeded())->not->toThrow(\Exception::class);
    });

    it('handles incomplete request data structure', function () {
        $requestCollector = Mockery::mock(RequestCollector::class);
        $requestCollector->shouldReceive('getData')->andReturn([]); // Missing 'request' and 'response' keys

        $this->registry->shouldReceive('get')->with('request')->andReturn($requestCollector);
        $this->registry->shouldReceive('get')->with('route')->andReturn(null);
        $this->registry->shouldReceive('get')->with('auth')->andReturn(null);
        $this->registry->shouldReceive('get')->with('logs')->andReturn(null);

        $data = $this->requestResources->getData();

        expect($data['request'])->toBe([]);
        expect($data['response'])->toBe([]);
    });

    it('handles incomplete log data structure', function () {
        $logCollector = Mockery::mock(LogCollector::class);
        $logCollector->shouldReceive('getData')->andReturn([]); // Missing 'logs' key

        $this->registry->shouldReceive('get')->with('request')->andReturn(null);
        $this->registry->shouldReceive('get')->with('route')->andReturn(null);
        $this->registry->shouldReceive('get')->with('auth')->andReturn(null);
        $this->registry->shouldReceive('get')->with('logs')->andReturn($logCollector);

        $data = $this->requestResources->getData();

        expect($data['logs'])->toBe([]);
    });
});

