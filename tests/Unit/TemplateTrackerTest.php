<?php

namespace ThiagoVieira\Saci\Tests\Unit;

use ThiagoVieira\Saci\TemplateTracker;
use ThiagoVieira\Saci\Support\DumpManager;
use ThiagoVieira\Saci\Support\DumpStorage;
use ThiagoVieira\Saci\SaciConfig;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as IlluminateView;
use Mockery;

beforeEach(function () {
    $this->dumpManager = Mockery::mock(DumpManager::class);
    $this->dumpStorage = Mockery::mock(DumpStorage::class);

    // Default mock expectations
    $this->dumpStorage->shouldReceive('generateRequestId')
        ->andReturn('test-request-id-123');

    $this->tracker = new TemplateTracker($this->dumpManager, $this->dumpStorage);
});

afterEach(function () {
    Mockery::close();
});

// ============================================================================
// 1. INITIALIZATION & STATE
// ============================================================================

describe('Initialization', function () {
    it('initializes with empty templates collection', function () {
        expect($this->tracker->getTemplates())->toBeArray()->toBeEmpty();
    });

    it('initializes with total count of zero', function () {
        expect($this->tracker->getTotal())->toBe(0);
    });

    it('generates unique request ID on construction', function () {
        $dumpManager = Mockery::mock(DumpManager::class);
        $dumpStorage = Mockery::mock(DumpStorage::class);
        $dumpStorage->shouldReceive('generateRequestId')
            ->once()
            ->andReturn('req-abc123');

        $tracker = new TemplateTracker($dumpManager, $dumpStorage);

        expect($tracker->getRequestId())->toBe('req-abc123');
    });

    it('generates different request IDs for different instances', function () {
        $dumpManager = Mockery::mock(DumpManager::class);
        $dumpStorage = Mockery::mock(DumpStorage::class);
        $dumpStorage->shouldReceive('generateRequestId')
            ->twice()
            ->andReturn('req-001', 'req-002');

        $tracker1 = new TemplateTracker($dumpManager, $dumpStorage);
        $tracker2 = new TemplateTracker($dumpManager, $dumpStorage);

        expect($tracker1->getRequestId())->not->toBe($tracker2->getRequestId());
    });
});

// ============================================================================
// 2. VIEW REGISTRATION & TRACKING
// ============================================================================

describe('View Registration', function () {
    it('registers view creator and composer callbacks', function () {
        View::shouldReceive('creator')
            ->once()
            ->with('*', Mockery::type('Closure'));

        View::shouldReceive('composer')
            ->once()
            ->with('*', Mockery::type('Closure'));

        $this->tracker->register();
    });
});

describe('View Tracking - Basic', function () {
    beforeEach(function () {
        config(['saci.performance_tracking' => true]);

        $this->view = Mockery::mock(IlluminateView::class);
        $this->view->shouldReceive('getPath')
            ->andReturn(base_path('resources/views/welcome.blade.php'));
        $this->view->shouldReceive('getData')
            ->andReturn(['user' => 'John']);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id-123');
    });

    it('tracks view with path and data', function () {
        // Directly test trackViewEnd via reflection (more reliable for unit test)
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();

        expect($templates)->toHaveCount(1);
        expect($templates[0])->toHaveKeys(['path', 'data']);
    });

    it('converts absolute path to relative path', function () {
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();

        expect($templates[0]['path'])->toBe('resources/views/welcome.blade.php');
    });

    it('increments total count when tracking views', function () {
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);

        $method->invoke($this->tracker, $this->view);
        expect($this->tracker->getTotal())->toBe(1);

        $method->invoke($this->tracker, $this->view);
        expect($this->tracker->getTotal())->toBe(2);
    });
});

// ============================================================================
// 3. PERFORMANCE TRACKING
// ============================================================================

describe('Performance Tracking', function () {
    beforeEach(function () {
        $this->view = Mockery::mock(IlluminateView::class);
        $this->view->shouldReceive('getPath')
            ->andReturn(base_path('resources/views/test.blade.php'));
        $this->view->shouldReceive('getData')->andReturn([]);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
    });

    it('tracks duration when performance tracking is enabled', function () {
        config(['saci.performance_tracking' => true]);

        $reflection = new \ReflectionClass($this->tracker);
        $startMethod = $reflection->getMethod('trackViewStart');
        $endMethod = $reflection->getMethod('trackViewEnd');
        $startMethod->setAccessible(true);
        $endMethod->setAccessible(true);

        $startMethod->invoke($this->tracker, $this->view);
        usleep(1000); // 1ms delay
        $endMethod->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();

        expect($templates[0])->toHaveKey('duration');
        expect($templates[0]['duration'])->toBeGreaterThan(0);
    });

    it('does not track duration when performance tracking is disabled', function () {
        config(['saci.performance_tracking' => false]);

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();

        expect($templates[0])->not->toHaveKey('duration');
    });

    it('calculates duration in milliseconds', function () {
        config(['saci.performance_tracking' => true]);

        $reflection = new \ReflectionClass($this->tracker);
        $startMethod = $reflection->getMethod('trackViewStart');
        $endMethod = $reflection->getMethod('trackViewEnd');
        $startMethod->setAccessible(true);
        $endMethod->setAccessible(true);

        $startMethod->invoke($this->tracker, $this->view);
        usleep(5000); // 5ms delay
        $endMethod->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $duration = $templates[0]['duration'];

        expect($duration)->toBeGreaterThanOrEqual(4.0);
        expect($duration)->toBeLessThan(20.0); // Allow some variance
    });

    it('rounds duration to 2 decimal places', function () {
        config(['saci.performance_tracking' => true]);

        $reflection = new \ReflectionClass($this->tracker);
        $startMethod = $reflection->getMethod('trackViewStart');
        $endMethod = $reflection->getMethod('trackViewEnd');
        $startMethod->setAccessible(true);
        $endMethod->setAccessible(true);

        $startMethod->invoke($this->tracker, $this->view);
        $endMethod->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $duration = $templates[0]['duration'];

        // Check if rounded to 2 decimal places
        expect($duration)->toBe(round($duration, 2));
    });

    it('handles missing start time gracefully', function () {
        config(['saci.performance_tracking' => true]);

        // Call trackViewEnd without trackViewStart
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();

        expect($templates[0]['duration'])->toBe(0.0);
    });
});

// ============================================================================
// 4. DATA FILTERING & NORMALIZATION
// ============================================================================

describe('Data Filtering', function () {
    beforeEach(function () {
        $this->view = Mockery::mock(IlluminateView::class);
        $this->view->shouldReceive('getPath')
            ->andReturn(base_path('resources/views/test.blade.php'));

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');
    });

    it('filters Laravel globals from view data', function () {
        config(['saci.performance_tracking' => false]);

        $this->view->shouldReceive('getData')->andReturn([
            '__env' => 'test-env',
            'app' => 'test-app',
            'errors' => [],
            '__data' => [],
            '__path' => '/path',
            'user' => 'John',
        ]);

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        expect($data)->toHaveKey('user');
        expect($data)->not->toHaveKey('__env');
        expect($data)->not->toHaveKey('app');
        expect($data)->not->toHaveKey('errors');
        expect($data)->not->toHaveKey('__data');
        expect($data)->not->toHaveKey('__path');
    });

    it('filters hidden fields from configuration', function () {
        config(['saci.performance_tracking' => false]);
        config(['saci.hidden_fields' => ['password', 'token']]);

        $this->view->shouldReceive('getData')->andReturn([
            'user' => 'John',
            'password' => 'secret123',
            'token' => 'abc-token',
        ]);

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        expect($data)->toHaveKey('user');
        expect($data)->not->toHaveKey('password');
        expect($data)->not->toHaveKey('token');
    });

    it('filters ignore_view_keys from configuration', function () {
        config(['saci.performance_tracking' => false]);
        config(['saci.ignore_view_keys' => ['internal', 'system']]);

        $this->view->shouldReceive('getData')->andReturn([
            'user' => 'John',
            'internal' => 'internal-data',
            'system' => 'system-data',
        ]);

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        expect($data)->toHaveKey('user');
        expect($data)->not->toHaveKey('internal');
        expect($data)->not->toHaveKey('system');
    });

    it('masks sensitive keys from configuration', function () {
        config(['saci.performance_tracking' => false]);
        // Use keys that are NOT in default hidden_fields
        config(['saci.mask_keys' => ['ssn', 'credit_card']]);
        // Clear hidden_fields to prevent default filtering
        config(['saci.hide_data_fields' => []]);

        $this->view->shouldReceive('getData')->andReturn([
            'user' => 'John',
            'ssn' => '123-45-6789',
            'credit_card' => '1234-5678-9012-3456',
        ]);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        // Check that masked keys have expected structure
        expect($data)->toHaveKey('user'); // Non-masked key should exist
        expect($data)->toHaveKey('ssn');
        expect($data['ssn'])->toHaveKey('preview');
        expect($data['ssn']['preview'])->toBe('[masked]');
        expect($data['ssn']['dump_id'])->toBeNull();

        expect($data)->toHaveKey('credit_card');
        expect($data['credit_card']['preview'])->toBe('[masked]');
    });

    it('masks keys matching regex patterns', function () {
        config(['saci.performance_tracking' => false]);
        // Use keys that are NOT in default hidden_fields
        config(['saci.mask_keys' => ['/^bank_/', '/_number$/']]);
        // Clear hidden_fields to prevent default filtering
        config(['saci.hide_data_fields' => []]);

        $this->view->shouldReceive('getData')->andReturn([
            'user' => 'John',
            'bank_account' => '123456',
            'bank_routing' => '987654',
            'phone_number' => '555-1234',
        ]);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        expect($data)->toHaveKey('bank_account');
        expect($data['bank_account']['preview'])->toBe('[masked]');
        expect($data)->toHaveKey('bank_routing');
        expect($data['bank_routing']['preview'])->toBe('[masked]');
        expect($data)->toHaveKey('phone_number');
        expect($data['phone_number']['preview'])->toBe('[masked]');
    });
});

describe('Data Normalization', function () {
    beforeEach(function () {
        config(['saci.performance_tracking' => false]);

        $this->view = Mockery::mock(IlluminateView::class);
        $this->view->shouldReceive('getPath')
            ->andReturn(base_path('resources/views/test.blade.php'));
    });

    it('normalizes values using DumpManager', function () {
        $this->view->shouldReceive('getData')->andReturn(['user' => 'John']);

        $this->dumpManager->shouldReceive('buildPreview')
            ->once()
            ->with('John')
            ->andReturn('string-preview');

        $this->dumpManager->shouldReceive('storeDump')
            ->once()
            ->with('test-request-id-123', 'John')
            ->andReturn('dump-id-john');

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        expect($data['user'])->toHaveKeys(['type', 'preview', 'dump_id']);
        expect($data['user']['type'])->toBe('string');
        expect($data['user']['preview'])->toBe('string-preview');
        expect($data['user']['dump_id'])->toBe('dump-id-john');
    });

    it('handles object normalization', function () {
        $object = new \stdClass();
        $object->name = 'Test';

        $this->view->shouldReceive('getData')->andReturn(['obj' => $object]);

        $this->dumpManager->shouldReceive('buildPreview')
            ->with($object)
            ->andReturn('object-preview');

        $this->dumpManager->shouldReceive('storeDump')
            ->with('test-request-id-123', $object)
            ->andReturn('dump-id-obj');

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        expect($data['obj']['type'])->toBe('stdClass');
        expect($data['obj']['preview'])->toBe('object-preview');
    });

    it('handles unserializable values gracefully', function () {
        // Create a resource (unserializable)
        $resource = fopen('php://memory', 'r');

        $this->view->shouldReceive('getData')->andReturn(['resource' => $resource]);

        $this->dumpManager->shouldReceive('buildPreview')
            ->andThrow(new \Exception('Cannot serialize resource'));

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $this->view);

        $templates = $this->tracker->getTemplates();
        $data = $templates[0]['data'];

        expect($data['resource']['preview'])->toBe('unserializable');
        expect($data['resource']['dump_id'])->toBeNull();

        fclose($resource);
    });
});

// ============================================================================
// 5. SACI VIEW RECURSION PREVENTION
// ============================================================================

describe('Recursion Prevention', function () {
    beforeEach(function () {
        config(['saci.performance_tracking' => true]);

        $this->saciView = Mockery::mock(IlluminateView::class);
        $this->saciView->shouldReceive('getPath')
            ->andReturn(base_path('vendor/thiago-vieira/saci/src/Resources/views/debug-bar.blade.php'));
    });

    it('detects Saci views from vendor path', function () {
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('isSaciView');
        $method->setAccessible(true);

        $result = $method->invoke($this->tracker, 'vendor/thiago-vieira/saci/src/Resources/views/debug-bar.blade.php');

        expect($result)->toBeTrue();
    });

    it('detects Saci views from relative path', function () {
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('isSaciView');
        $method->setAccessible(true);

        $result = $method->invoke($this->tracker, '/saci/src/Resources/views/panel.blade.php');

        expect($result)->toBeTrue();
    });

    it('does not track Saci views to prevent recursion', function () {
        $reflection = new \ReflectionClass($this->tracker);
        $startMethod = $reflection->getMethod('trackViewStart');
        $endMethod = $reflection->getMethod('trackViewEnd');
        $startMethod->setAccessible(true);
        $endMethod->setAccessible(true);

        $startMethod->invoke($this->tracker, $this->saciView);
        $endMethod->invoke($this->tracker, $this->saciView);

        expect($this->tracker->getTemplates())->toBeEmpty();
        expect($this->tracker->getTotal())->toBe(0);
    });

    it('tracks user views normally', function () {
        config(['saci.performance_tracking' => false]);

        $userView = Mockery::mock(IlluminateView::class);
        $userView->shouldReceive('getPath')
            ->andReturn(base_path('resources/views/welcome.blade.php'));
        $userView->shouldReceive('getData')->andReturn([]);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $userView);

        expect($this->tracker->getTemplates())->toHaveCount(1);
    });
});

// ============================================================================
// 6. RESET & CLEAR FUNCTIONALITY
// ============================================================================

describe('State Management', function () {
    it('resets state for new request', function () {
        config(['saci.performance_tracking' => false]);

        $view = Mockery::mock(IlluminateView::class);
        $view->shouldReceive('getPath')->andReturn(base_path('resources/views/test.blade.php'));
        $view->shouldReceive('getData')->andReturn(['data' => 'test']);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        // Track a view
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $view);

        expect($this->tracker->getTotal())->toBe(1);

        // Reset for new request - need to set up new mock expectation BEFORE beforeEach
        // Create new tracker with fresh mocks for this test
        $newDumpStorage = Mockery::mock(DumpStorage::class);
        $newDumpStorage->shouldReceive('generateRequestId')
            ->once()
            ->andReturn('new-request-id-456');

        // Use reflection to replace the dumpStorage instance
        $reflection = new \ReflectionClass($this->tracker);
        $property = $reflection->getProperty('storage');
        $property->setAccessible(true);
        $property->setValue($this->tracker, $newDumpStorage);

        $this->tracker->resetForRequest();

        expect($this->tracker->getTemplates())->toBeEmpty();
        expect($this->tracker->getTotal())->toBe(0);
        expect($this->tracker->getRequestId())->toBe('new-request-id-456');
    });

    it('clears all tracked templates', function () {
        config(['saci.performance_tracking' => false]);

        $view = Mockery::mock(IlluminateView::class);
        $view->shouldReceive('getPath')->andReturn(base_path('resources/views/test.blade.php'));
        $view->shouldReceive('getData')->andReturn([]);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        // Track views
        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);

        $method->invoke($this->tracker, $view);
        $method->invoke($this->tracker, $view);

        expect($this->tracker->getTotal())->toBe(2);

        $this->tracker->clear();

        expect($this->tracker->getTemplates())->toBeEmpty();
        expect($this->tracker->getTotal())->toBe(0);
    });

    it('maintains request ID after clear', function () {
        $originalId = $this->tracker->getRequestId();

        $this->tracker->clear();

        expect($this->tracker->getRequestId())->toBe($originalId);
    });
});

// ============================================================================
// 7. EDGE CASES
// ============================================================================

describe('Edge Cases', function () {
    it('handles views without path gracefully', function () {
        config(['saci.performance_tracking' => true]);

        $view = Mockery::mock(IlluminateView::class);
        $view->shouldReceive('getPath')->andReturn(null);

        $reflection = new \ReflectionClass($this->tracker);
        $startMethod = $reflection->getMethod('trackViewStart');
        $endMethod = $reflection->getMethod('trackViewEnd');
        $startMethod->setAccessible(true);
        $endMethod->setAccessible(true);

        $startMethod->invoke($this->tracker, $view);
        $endMethod->invoke($this->tracker, $view);

        expect($this->tracker->getTemplates())->toBeEmpty();
    });

    it('handles views with empty data', function () {
        config(['saci.performance_tracking' => false]);

        $view = Mockery::mock(IlluminateView::class);
        $view->shouldReceive('getPath')->andReturn(base_path('resources/views/empty.blade.php'));
        $view->shouldReceive('getData')->andReturn([]);

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $view);

        $templates = $this->tracker->getTemplates();

        expect($templates)->toHaveCount(1);
        expect($templates[0]['data'])->toBeArray()->toBeEmpty();
    });

    it('handles nested view rendering', function () {
        config(['saci.performance_tracking' => false]);

        $parentView = Mockery::mock(IlluminateView::class);
        $parentView->shouldReceive('getPath')->andReturn(base_path('resources/views/parent.blade.php'));
        $parentView->shouldReceive('getData')->andReturn(['parent' => 'data']);

        $childView = Mockery::mock(IlluminateView::class);
        $childView->shouldReceive('getPath')->andReturn(base_path('resources/views/child.blade.php'));
        $childView->shouldReceive('getData')->andReturn(['child' => 'data']);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);

        $method->invoke($this->tracker, $parentView);
        $method->invoke($this->tracker, $childView);

        expect($this->tracker->getTotal())->toBe(2);
    });

    it('handles special characters in view paths', function () {
        config(['saci.performance_tracking' => false]);

        $view = Mockery::mock(IlluminateView::class);
        $view->shouldReceive('getPath')->andReturn(base_path('resources/views/测试-тест-τεστ.blade.php'));
        $view->shouldReceive('getData')->andReturn([]);

        $this->dumpManager->shouldReceive('buildPreview')->andReturn('preview');
        $this->dumpManager->shouldReceive('storeDump')->andReturn('dump-id');

        $reflection = new \ReflectionClass($this->tracker);
        $method = $reflection->getMethod('trackViewEnd');
        $method->setAccessible(true);
        $method->invoke($this->tracker, $view);

        $templates = $this->tracker->getTemplates();

        expect($templates[0]['path'])->toContain('测试-тест-τεστ.blade.php');
    });
});

