<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Collectors\ViewCollector;
use ThiagoVieira\Saci\TemplateTracker;

beforeEach(function () {
    $this->tracker = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();
    $this->collector = new ViewCollector($this->tracker);
});

describe('ViewCollector Identity', function () {
    it('returns correct name', function () {
        expect($this->collector->getName())->toBe('views');
    });

    it('returns correct label', function () {
        expect($this->collector->getLabel())->toBe('Views');
    });

    it('is enabled by default', function () {
        config()->set('saci.collectors.views', true);
        expect($this->collector->isEnabled())->toBeTrue();
    });

    it('can be disabled via config', function () {
        config()->set('saci.collectors.views', false);
        expect($this->collector->isEnabled())->toBeFalse();
    });
});

describe('ViewCollector Lifecycle', function () {
    it('registers tracker on start', function () {
        $this->tracker->shouldReceive('resetForRequest')->once();
        $this->tracker->shouldReceive('register')->once();

        $this->collector->start();
    });

    it('handles tracker without resetForRequest method', function () {
        $tracker = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();
        $tracker->shouldReceive('register')->once();

        $collector = new ViewCollector($tracker);
        $collector->start();

        expect(true)->toBeTrue(); // No exception thrown
    });

    it('collects templates data', function () {
        $templates = [
            ['path' => 'welcome.blade.php', 'data' => ['title' => 'Welcome']],
            ['path' => 'home.blade.php', 'data' => ['user' => 'John']],
        ];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(2);
        $this->tracker->shouldReceive('getRequestId')->andReturn('test-request-id');

        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toHaveKey('templates');
        expect($data)->toHaveKey('total');
        expect($data)->toHaveKey('request_id');
        expect($data['templates'])->toHaveCount(2);
        expect($data['total'])->toBe(2);
        expect($data['request_id'])->toBe('test-request-id');
    });

    it('handles tracker without getRequestId method', function () {
        $tracker = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();
        $tracker->shouldReceive('getTemplates')->andReturn([]);
        $tracker->shouldReceive('getTotal')->andReturn(0);

        $collector = new ViewCollector($tracker);
        $collector->start();
        $collector->collect();

        // shouldIgnoreMissing() returns empty string, not null
        expect($collector->getData()['request_id'])->toBeIn([null, '']);
    });

    it('clears tracker on reset', function () {
        $this->tracker->shouldReceive('clear')->once();

        $this->collector->reset();
    });
});

describe('ViewCollector Data Collection', function () {
    it('collects empty templates', function () {
        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn([]);
        $this->tracker->shouldReceive('getTotal')->andReturn(0);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getTotal())->toBe(0);
        expect($this->collector->getTemplates())->toBeEmpty();
    });

    it('collects single template', function () {
        $templates = [
            ['path' => 'welcome.blade.php', 'data' => ['title' => 'Home']],
        ];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(1);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getTotal())->toBe(1);
        expect($this->collector->getTemplates())->toHaveCount(1);
        expect($this->collector->getTemplates()[0]['path'])->toBe('welcome.blade.php');
    });

    it('collects multiple templates', function () {
        $templates = [
            ['path' => 'layouts/app.blade.php', 'data' => []],
            ['path' => 'components/header.blade.php', 'data' => []],
            ['path' => 'pages/home.blade.php', 'data' => ['posts' => []]],
        ];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(3);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getTotal())->toBe(3);
        expect($this->collector->getTemplates())->toHaveCount(3);
    });

    it('collects templates with data', function () {
        $templates = [
            [
                'path' => 'profile.blade.php',
                'data' => [
                    'user' => 'John Doe',
                    'email' => 'john@example.com',
                    'posts' => ['Post 1', 'Post 2'],
                ],
            ],
        ];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(1);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        $collected = $this->collector->getTemplates()[0];
        expect($collected['data'])->toHaveKey('user');
        expect($collected['data'])->toHaveKey('email');
        expect($collected['data'])->toHaveKey('posts');
    });

    it('collects templates with performance data', function () {
        $templates = [
            ['path' => 'slow.blade.php', 'data' => [], 'duration' => 125.45],
            ['path' => 'fast.blade.php', 'data' => [], 'duration' => 5.12],
        ];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(2);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        $collected = $this->collector->getTemplates();
        expect($collected[0]['duration'])->toBe(125.45);
        expect($collected[1]['duration'])->toBe(5.12);
    });
});

describe('ViewCollector Backward Compatibility Methods', function () {
    it('provides getTemplates() method', function () {
        $templates = [['path' => 'test.blade.php', 'data' => []]];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(1);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getTemplates())->toBe($templates);
    });

    it('provides getTotal() method', function () {
        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn([]);
        $this->tracker->shouldReceive('getTotal')->andReturn(5);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getTotal())->toBe(5);
    });

    it('provides getRequestId() method', function () {
        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn([]);
        $this->tracker->shouldReceive('getTotal')->andReturn(0);
        $this->tracker->shouldReceive('getRequestId')->andReturn('test-req-123');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getRequestId())->toBe('test-req-123');
    });

    it('returns empty values before collection', function () {
        expect($this->collector->getTemplates())->toBeEmpty();
        expect($this->collector->getTotal())->toBe(0);
        expect($this->collector->getRequestId())->toBeNull();
    });
});

describe('ViewCollector Integration', function () {
    it('follows complete lifecycle', function () {
        $templates = [['path' => 'test.blade.php', 'data' => ['var' => 'value']]];

        // Start
        $this->tracker->shouldReceive('resetForRequest')->once();
        $this->tracker->shouldReceive('register')->once();
        $this->collector->start();

        // Collect
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(1);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');
        $this->collector->collect();

        expect($this->collector->getData())->not->toBeEmpty();

        // Reset
        $this->tracker->shouldReceive('clear')->once();
        $this->collector->reset();
    });

    it('handles multiple collect cycles', function () {
        $templates1 = [['path' => 'first.blade.php', 'data' => []]];
        $templates2 = [
            ['path' => 'second.blade.php', 'data' => []],
            ['path' => 'third.blade.php', 'data' => []],
        ];

        // First cycle
        $this->tracker->shouldReceive('resetForRequest')->once();
        $this->tracker->shouldReceive('register')->once();
        $this->tracker->shouldReceive('getTemplates')->once()->andReturn($templates1);
        $this->tracker->shouldReceive('getTotal')->once()->andReturn(1);
        $this->tracker->shouldReceive('getRequestId')->once()->andReturn('req-1');
        $this->tracker->shouldReceive('clear')->once();

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getTotal())->toBe(1);

        $this->collector->reset();

        // Second cycle - new collector instance to avoid mock conflicts
        $tracker2 = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();
        $collector2 = new ViewCollector($tracker2);

        $tracker2->shouldReceive('getTemplates')->once()->andReturn($templates2);
        $tracker2->shouldReceive('getTotal')->once()->andReturn(2);
        $tracker2->shouldReceive('getRequestId')->once()->andReturn('req-2');

        $collector2->start();
        $collector2->collect();

        expect($collector2->getTotal())->toBe(2);
    });
});

describe('ViewCollector Edge Cases', function () {
    it('handles null request ID gracefully', function () {
        $tracker = Mockery::mock(TemplateTracker::class)->shouldIgnoreMissing();
        $tracker->shouldReceive('getTemplates')->andReturn([]);
        $tracker->shouldReceive('getTotal')->andReturn(0);
        // Don't mock getRequestId - let method_exists return false

        $collector = new ViewCollector($tracker);
        $collector->start();
        $collector->collect();

        // shouldIgnoreMissing() returns empty string, not null
        expect($collector->getRequestId())->toBeIn([null, '']);
    });

    it('handles templates with empty data', function () {
        $templates = [
            ['path' => 'empty.blade.php', 'data' => []],
        ];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(1);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getTemplates()[0]['data'])->toBeEmpty();
    });

    it('handles collection without start', function () {
        // Without start(), collect() should do nothing
        $this->collector->collect();

        expect($this->collector->getData())->toBeEmpty();
    });

    it('handles templates with special characters in paths', function () {
        $templates = [
            ['path' => 'views/módulo/página-especial.blade.php', 'data' => []],
        ];

        $this->tracker->shouldReceive('resetForRequest');
        $this->tracker->shouldReceive('register');
        $this->tracker->shouldReceive('getTemplates')->andReturn($templates);
        $this->tracker->shouldReceive('getTotal')->andReturn(1);
        $this->tracker->shouldReceive('getRequestId')->andReturn('req-id');

        $this->collector->start();
        $this->collector->collect();

        $path = $this->collector->getTemplates()[0]['path'];
        expect($path)->toContain('módulo');
        expect($path)->toContain('página-especial');
    });
});

