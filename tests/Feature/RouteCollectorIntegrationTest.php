<?php

declare(strict_types=1);

namespace ThiagoVieira\Saci\Tests\Feature;

use Illuminate\Support\Facades\Route;
use ThiagoVieira\Saci\Collectors\RouteCollector;

/**
 * Integration tests for RouteCollector with real Laravel routes.
 *
 * These tests complement the unit tests by validating RouteCollector
 * behavior with actual HTTP requests and real Laravel routing system.
 *
 * Why these tests matter:
 * - Unit tests mock Route objects, which can miss real-world edge cases
 * - Real routes go through Laravel's full routing pipeline
 * - Validates actual middleware, parameters, and controller resolution
 */
describe('RouteCollector Real World Integration', function () {
    it('collects data from simple GET route', function () {
        Route::get('/test-simple', function () {
            return 'test';
        })->name('test.simple');

        $response = $this->get('/test-simple');
        $response->assertOk();

        $collector = app(RouteCollector::class);
        $collector->setRequest(request());
        $collector->collect();

        $data = $collector->getData();
        expect($data['name'])->toBe('test.simple');
        expect($data['uri'])->toBe('test-simple');
        expect($data['methods'])->toContain('GET');
    });

    it('collects data from route with parameters', function () {
        Route::get('/users/{id}/posts/{post}', function ($id, $post) {
            return "User $id Post $post";
        })->name('users.posts.show')->where(['id' => '[0-9]+', 'post' => '[0-9]+']);

        $response = $this->get('/users/123/posts/456');
        $response->assertOk();

        $collector = app(RouteCollector::class);
        $collector->setRequest(request());
        $collector->collect();

        $data = $collector->getData();
        expect($data['name'])->toBe('users.posts.show');
        expect($data['parameters'])->toHaveKey('id', '123');
        expect($data['parameters'])->toHaveKey('post', '456');
        expect($data['where'])->toHaveKey('id');
        expect($data['where'])->toHaveKey('post');
    });

    it('collects middleware from real route', function () {
        Route::get('/protected', function () {
            return 'protected';
        })->name('protected.route')->middleware(['web']);

        // Create a bound request through the actual routing
        $request = \Illuminate\Http\Request::create('/protected', 'GET');
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn() => $route);

        $collector = app(RouteCollector::class);
        $collector->setRequest($request);
        $collector->start(); // Must call start() before collect()
        $collector->collect();

        $data = $collector->getData();
        expect($data)->toHaveKey('middleware');
        expect($data['middleware'])->toBeArray();
    });

    it('handles route groups with prefix', function () {
        Route::prefix('api/v1')->group(function () {
            Route::get('/health', function () {
                return ['status' => 'ok'];
            })->name('api.health');
        });

        $response = $this->get('/api/v1/health');
        $response->assertOk();

        $collector = app(RouteCollector::class);
        $collector->setRequest(request());
        $collector->collect();

        $data = $collector->getData();
        expect($data['name'])->toBe('api.health');
        expect($data['uri'])->toBe('api/v1/health');
        expect($data['prefix'])->toBe('api/v1');
    });

    it('collects data from POST route', function () {
        Route::post('/api/users', function () {
            return ['created' => true];
        })->name('api.users.store');

        $response = $this->post('/api/users', ['name' => 'Test']);
        $response->assertOk();

        $collector = app(RouteCollector::class);
        $collector->setRequest(request());
        $collector->collect();

        $data = $collector->getData();
        expect($data['name'])->toBe('api.users.store');
        expect($data['methods'])->toContain('POST');
    });
});

/**
 * Edge case tests that are better suited for integration testing.
 */
describe('RouteCollector Integration Edge Cases', function () {
    it('handles routes with optional parameters', function () {
        Route::get('/search/{category?}', function ($category = 'all') {
            return "Category: $category";
        })->name('search');

        $response = $this->get('/search');
        $response->assertOk();

        $collector = app(RouteCollector::class);
        $collector->setRequest(request());
        $collector->collect();

        $data = $collector->getData();
        expect($data['name'])->toBe('search');
        expect($data['uri'])->toContain('category?');
    });

    it('handles fallback routes', function () {
        Route::fallback(function () {
            return '404';
        });

        $response = $this->get('/non-existent-route');
        $response->assertOk();

        $collector = app(RouteCollector::class);
        $collector->setRequest(request());
        $collector->collect();

        $data = $collector->getData();
        expect($data)->toBeArray();
        expect($data['uri'])->toContain('fallback');
    });
});

