# Contributing to Saci

Thank you for considering contributing to Saci! This guide will help you get started.

## Development Setup

### Requirements

- PHP 8.2 or higher
- Composer
- Xdebug (for code coverage)

### Installation

```bash
git clone https://github.com/thiago-vieira/saci.git
cd saci
composer install
```

## Running Tests

### All Tests

```bash
./vendor/bin/pest
```

### With Code Coverage

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage
```

### Generate HTML Coverage Report

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-html=coverage/html
open coverage/html/index.html
```

### Specific Test Suite

```bash
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Feature
```

### Watch Mode (re-run on file change)

```bash
./vendor/bin/pest --watch
```

## Code Quality Standards

### Coverage Requirements

- **Minimum**: 85% overall code coverage
- **Target**: 90%+ for critical files
- All new features must include tests
- Bug fixes should include regression tests

### Test Structure

Tests are organized into:

- `tests/Unit/` - Unit tests for individual classes
- `tests/Feature/` - Integration tests with Laravel components

### Writing Good Tests

1. **Descriptive Names**: Use `it()` with clear, natural language descriptions
2. **Arrange-Act-Assert**: Structure tests with clear setup, execution, and verification
3. **Test Behavior, Not Implementation**: Focus on what the code does, not how
4. **Edge Cases**: Include tests for error handling and boundary conditions

Example:

```php
describe('RequestCollector', function () {
    it('collects HTTP method correctly', function () {
        $request = Request::create('/test', 'POST');
        $collector = new RequestCollector();
        
        $collector->start($request);
        $collector->collect();
        
        $data = $collector->getData();
        expect($data['method'])->toBe('POST');
    });
});
```

## Testing Best Practices

### Use Test Helpers

The `tests/Pest.php` file contains helper functions. Use them!

```php
// Create a route bound to a request
$route = createBoundRoute($request, '/test/{id}', ['id' => '123']);

// Create a simple route
$route = createSimpleRoute('/test', 'TestController@index');
```

### Mock External Dependencies

```php
use Mockery;

$mockDisk = Mockery::mock(\Illuminate\Filesystem\Filesystem::class);
$mockDisk->shouldReceive('exists')->andReturn(true);
```

### Test Real Laravel Integration

For feature tests, use Orchestra Testbench:

```php
use Orchestra\Testbench\TestCase;

class MyFeatureTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \ThiagoVieira\Saci\SaciServiceProvider::class,
        ];
    }
}
```

## Coverage Goals by File Type

| File Type | Target Coverage |
|-----------|----------------|
| Collectors | 95%+ |
| Support Classes | 90%+ |
| Controllers | 95%+ |
| Service Providers | 95%+ |
| Config | 100% |

## Known Testing Challenges

### Difficult to Test (and that's OK)

Some code is inherently difficult to test with unit tests:

1. **Exception Handlers**: Deep in catch blocks with external dependencies
2. **File I/O Errors**: Hard to simulate `fopen()` failures consistently
3. **Framework Internals**: Deep Laravel/Symphony integration points

For these cases:
- Document why testing is difficult
- Add `@codeCoverageIgnore` if appropriate
- Focus on integration tests instead

## Submitting Changes

### Before Submitting a PR

1. ✅ All tests pass: `./vendor/bin/pest`
2. ✅ Coverage maintained or improved
3. ✅ New features have tests
4. ✅ Documentation updated (if needed)

### PR Guidelines

- Keep PRs focused on a single feature/fix
- Include test coverage in your PR description
- Reference any related issues
- Update CHANGELOG.md (if applicable)

### Commit Message Format

```
<type>(<scope>): <subject>

<body>
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `test`: Adding/updating tests
- `docs`: Documentation changes
- `refactor`: Code refactoring
- `perf`: Performance improvements

Example:

```
feat(collectors): add N+1 query detection

- Implement query pattern normalization
- Add threshold configuration
- Include examples in detected patterns

Closes #42
```

## Questions?

Feel free to open an issue for:
- Questions about the codebase
- Suggestions for improvement
- Reporting bugs

Thank you for contributing! 🙏

