<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeCollectorData', function () {
    return $this
        ->toBeArray()
        ->toHaveKeys(['name', 'label', 'data', 'enabled']);
});

expect()->extend('toHaveDumpKeys', function () {
    return $this
        ->toBeArray()
        ->toHaveKeys(['preview', 'dump_id', 'request_id']);
});

expect()->extend('toBeValidDumpId', function () {
    return $this
        ->toBeString()
        ->toMatch('/^[a-f0-9]{32}$/');
});

expect()->extend('toBeValidRequestId', function () {
    return $this
        ->toBeString()
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// Note: Mock helpers are available directly:
// - Mockery::mock() for mocks
// - Mockery::spy() for spies
// - fixture() is built into Pest

/**
 * Create a fake HTTP request for testing.
 */
function fakeRequest(
    string $method = 'GET',
    string $uri = '/',
    array $parameters = [],
    array $headers = []
): Illuminate\Http\Request {
    return Illuminate\Http\Request::create($uri, $method, $parameters, [], [], [], null)
        ->headers->add($headers);
}

/**
 * Create a fake HTTP response for testing.
 */
function fakeResponse(
    string $content = '',
    int $status = 200,
    array $headers = []
): Illuminate\Http\Response {
    return new Illuminate\Http\Response($content, $status, $headers);
}

/**
 * Assert that a string contains HTML.
 */
function assertHtml(string $content): void
{
    expect($content)
        ->toContain('<html')
        ->or->toContain('<!DOCTYPE')
        ->or->toContain('<body');
}

/**
 * Strip HTML tags and return clean text.
 */
function stripHtml(string $html): string
{
    return strip_tags($html);
}

/*
|--------------------------------------------------------------------------
| Route Testing Helpers
|--------------------------------------------------------------------------
|
| Routes in Laravel require proper binding to work with parameters and
| other dynamic features. These helpers simulate what Laravel does
| internally during real HTTP requests, making route testing easier.
|
| Why we need these:
| - Route::parameters() throws LogicException if route is not bound
| - Binding simulates real Laravel request lifecycle
| - Reduces boilerplate in route-related tests
|
*/

/**
 * Creates a properly bound route for testing.
 *
 * Routes in Laravel require binding to work properly with parameters().
 * This helper simulates what Laravel does internally during a real request.
 *
 * IMPORTANT: If you need to add middleware, name, or where() clauses,
 * apply them to the $route AFTER calling this function, as the route
 * is returned unbounded initially to allow chaining.
 *
 * Example:
 *   [$route, $request] = createBoundRoute(
 *       ['GET'],
 *       '/users/{id}',
 *       fn() => 'test',
 *       ['id' => '123']
 *   );
 *   $route->where('id', '[0-9]+'); // Can be called after
 *
 * @param array<string> $methods HTTP methods ['GET', 'POST', etc]
 * @param string $uri Route URI pattern '/users/{id}'
 * @param mixed $action Controller action or Closure
 * @param array<string, mixed> $parameters Route parameters ['id' => '123']
 * @param array<string, string> $where Where constraints ['id' => '[0-9]+']
 * @return array{0: \Illuminate\Routing\Route, 1: \Illuminate\Http\Request}
 */
function createBoundRoute(
    array $methods,
    string $uri,
    $action,
    array $parameters = [],
    array $where = []
): array {
    $route = new \Illuminate\Routing\Route($methods, $uri, $action);

    // Apply where constraints BEFORE binding
    if (!empty($where)) {
        $route->where($where);
    }

    // Create request URI by replacing parameters
    $requestUri = $uri;
    foreach ($parameters as $key => $value) {
        $requestUri = str_replace("{{$key}}", (string) $value, $requestUri);
    }

    $request = \Illuminate\Http\Request::create($requestUri, $methods[0]);

    // Critical: Bind route to request (required for parameters() to work)
    $route->bind($request);
    $request->setRouteResolver(fn() => $route);

    return [$route, $request];
}

/**
 * Simplified version for routes without parameters.
 *
 * Example:
 *   [$route, $request] = createSimpleRoute(['GET'], '/users', fn() => 'test');
 *   $route->name('users.index');
 *
 * @param array<string> $methods HTTP methods
 * @param string $uri Route URI without parameters
 * @param mixed $action Controller action or Closure
 * @return array{0: \Illuminate\Routing\Route, 1: \Illuminate\Http\Request}
 */
function createSimpleRoute(array $methods, string $uri, $action): array
{
    return createBoundRoute($methods, $uri, $action);
}

