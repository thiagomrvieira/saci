# Saci Tests

Comprehensive test suite for the Saci Laravel Debug Bar package.

## 📊 Test Coverage

- ✅ **121 tests** passing
- ✅ **197 assertions**
- ✅ **~5.3s** execution time

## 🧪 Test Structure

```
tests/
├── TestCase.php                    # Base test class with Orchestra Testbench
├── Pest.php                        # Pest configuration & helpers
├── Fixtures/                       # Test fixtures (views, dumps, logs)
├── Unit/                           # Isolated unit tests
│   ├── Collectors/
│   │   └── BaseCollectorTest.php  # ✅ 28 tests
│   └── Support/
│       ├── CollectorRegistryTest.php  # ✅ 21 tests
│       ├── DumpManagerTest.php       # ✅ 44 tests
│       └── DumpStorageTest.php       # ✅ 28 tests
├── Feature/                        # Integration tests (TODO: Phase 2)
└── Architecture/                   # Architecture tests (TODO: Phase 5)
```

## 🚀 Running Tests

### Run all tests
```bash
./vendor/bin/pest
```

### Run with colors
```bash
./vendor/bin/pest --colors=always
```

### Run specific test file
```bash
./vendor/bin/pest tests/Unit/Support/CollectorRegistryTest.php
```

### Run specific test suite
```bash
./vendor/bin/pest --testsuite=Unit
```

### Run with coverage
```bash
./vendor/bin/pest --coverage
```

### Run with minimum coverage threshold
```bash
./vendor/bin/pest --coverage --min=80
```

### Watch mode (runs tests on file changes)
```bash
./vendor/bin/pest --watch
```

### Parallel execution
```bash
./vendor/bin/pest --parallel
```

## 📝 Test Conventions

### AAA Pattern (Arrange, Act, Assert)

```php
it('stores HTML dump successfully', function () {
    // Arrange
    $requestId = $this->storage->generateRequestId();
    $dumpId = $this->storage->generateDumpId();
    $html = '<div>Test dump content</div>';

    // Act
    $result = $this->storage->storeHtml($requestId, $dumpId, $html);

    // Assert
    expect($result)->toBeTrue();
});
```

### Descriptive Test Names

✅ **Good:**
```php
it('enforces per-request byte cap')
it('returns null for non-existent dump')
it('handles circular references gracefully')
```

❌ **Bad:**
```php
it('works')
it('test storage')
it('does something')
```

### Testing Edge Cases

Always test:
- ✅ Happy path (expected behavior)
- ✅ Empty inputs
- ✅ Null values
- ✅ Large datasets
- ✅ Special characters
- ✅ Error conditions

## 🎯 What's Being Tested (Phase 1 - Foundation)

### ✅ CollectorRegistry
- Registration and retrieval
- Fluent interface
- Filtering enabled/disabled collectors
- Lifecycle management (start/collect/reset)
- Data aggregation
- Edge cases (empty registry, duplicate names)

### ✅ BaseCollector
- Configuration (enable/disable via config)
- Lifecycle (start → collect → reset)
- Template method pattern
- Data management
- Multiple cycles
- Edge cases

### ✅ DumpStorage
- ID generation (request ID, dump ID)
- Path management
- HTML storage and retrieval
- Byte cap enforcement
- TTL-based cleanup
- Custom disk configuration
- Edge cases (empty HTML, large files, special chars)

### ✅ DumpManager
- Value cloning (Data objects)
- HTML rendering (expanded, no scripts/styles)
- Preview generation (truncation, single-line)
- Dump storage
- Custom limits configuration
- Edge cases (nested structures, circular refs, unicode)

## 🔜 Next Phases

### Phase 2: Collectors (Pending)
- ViewCollectorTest
- RequestCollectorTest
- RouteCollectorTest
- DatabaseCollectorTest (N+1 detection!)
- AuthCollectorTest
- LogCollectorTest

### Phase 3: Integration (Pending)
- MiddlewareIntegrationTest
- DebugBarInjectionTest
- CollectorsPipelineTest
- ConfigurationTest

### Phase 4: HTTP Layer (Pending)
- AssetServingTest
- DumpEndpointTest

### Phase 5: Architecture (Pending)
- ArchTest (ensures architectural rules)

## 🛠️ Test Helpers

### Custom Expectations

```php
expect($requestId)->toBeValidRequestId();
expect($dumpId)->toBeValidDumpId();
```

### Fake Requests/Responses

```php
$request = fakeRequest('POST', '/api/users', ['name' => 'John']);
$response = fakeResponse('<html>...</html>', 200);
```

### TestCase Helpers

```php
$this->disableSaci();
$this->enableCollector('views');
$this->disableCollector('database');
$this->disableAllCollectors();
```

## 📈 Code Quality

### Standards
- ✅ PSR-12 coding standard
- ✅ Strict types (`declare(strict_types=1)`)
- ✅ SOLID principles
- ✅ DRY (Don't Repeat Yourself)
- ✅ KISS (Keep It Simple, Stupid)

### Best Practices
- ✅ One assertion per test (when possible)
- ✅ Descriptive test names
- ✅ Isolated tests (no dependencies)
- ✅ Fast execution (~5s total)
- ✅ Comprehensive edge case coverage

## 🐛 Debugging Failed Tests

### See detailed error output
```bash
./vendor/bin/pest --verbose
```

### Stop on first failure
```bash
./vendor/bin/pest --stop-on-failure
```

### Run only failed tests
```bash
./vendor/bin/pest --retry
```

## 📚 Resources

- [Pest Documentation](https://pestphp.com)
- [Orchestra Testbench](https://github.com/orchestral/testbench)
- [Mockery Documentation](http://docs.mockery.io)
- [PHPUnit Assertions](https://phpunit.de/manual/current/en/assertions.html)

## 💡 Writing New Tests

1. Create test file in appropriate directory
2. Use `describe()` to group related tests
3. Use `it()` or `test()` for individual tests
4. Follow AAA pattern
5. Test edge cases
6. Keep tests isolated
7. Use meaningful names

Example template:

```php
<?php

declare(strict_types=1);

use Your\Namespace\YourClass;

beforeEach(function () {
    $this->instance = new YourClass();
});

describe('YourClass Feature Name', function () {
    it('does something expected', function () {
        // Arrange
        $input = 'test';

        // Act
        $result = $this->instance->doSomething($input);

        // Assert
        expect($result)->toBe('expected');
    });

    it('handles edge case', function () {
        expect($this->instance->doSomething(''))->toBeEmpty();
    });
});
```

---

**Built with ❤️ using [Pest PHP](https://pestphp.com)**



