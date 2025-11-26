<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Support\CollectorRegistry;
use ThiagoVieira\Saci\Collectors\Contracts\CollectorInterface;

beforeEach(function () {
    $this->registry = new CollectorRegistry();
});

describe('CollectorRegistry Registration', function () {
    it('starts with no collectors', function () {
        expect($this->registry->count())->toBe(0);
    });

    it('registers a collector', function () {
        $collector = mockCollector('test', 'Test Collector');

        $this->registry->register($collector);

        expect($this->registry->count())->toBe(1);
        expect($this->registry->has('test'))->toBeTrue();
    });

    it('returns itself for fluent registration', function () {
        $collector = mockCollector('test', 'Test Collector');

        $result = $this->registry->register($collector);

        expect($result)->toBe($this->registry);
    });

    it('registers multiple collectors', function () {
        $collector1 = mockCollector('test1', 'Test 1');
        $collector2 = mockCollector('test2', 'Test 2');

        $this->registry
            ->register($collector1)
            ->register($collector2);

        expect($this->registry->count())->toBe(2);
        expect($this->registry->has('test1'))->toBeTrue();
        expect($this->registry->has('test2'))->toBeTrue();
    });

    it('replaces collector with same name', function () {
        $collector1 = mockCollector('test', 'Test 1');
        $collector2 = mockCollector('test', 'Test 2');

        $this->registry->register($collector1);
        $this->registry->register($collector2);

        expect($this->registry->count())->toBe(1);
        expect($this->registry->get('test'))->toBe($collector2);
    });
});

describe('CollectorRegistry Retrieval', function () {
    it('retrieves registered collector by name', function () {
        $collector = mockCollector('test', 'Test Collector');
        $this->registry->register($collector);

        $retrieved = $this->registry->get('test');

        expect($retrieved)->toBe($collector);
    });

    it('returns null for non-existent collector', function () {
        $retrieved = $this->registry->get('nonexistent');

        expect($retrieved)->toBeNull();
    });

    it('checks if collector exists', function () {
        $collector = mockCollector('test', 'Test Collector');
        $this->registry->register($collector);

        expect($this->registry->has('test'))->toBeTrue();
        expect($this->registry->has('nonexistent'))->toBeFalse();
    });

    it('returns all registered collectors', function () {
        $collector1 = mockCollector('test1', 'Test 1');
        $collector2 = mockCollector('test2', 'Test 2');

        $this->registry->register($collector1)->register($collector2);

        $all = $this->registry->all();

        expect($all)->toHaveCount(2);
        expect($all->get('test1'))->toBe($collector1);
        expect($all->get('test2'))->toBe($collector2);
    });
});

describe('CollectorRegistry Filtering', function () {
    it('filters enabled collectors', function () {
        $enabled1 = mockCollector('enabled1', 'Enabled 1', true);
        $enabled2 = mockCollector('enabled2', 'Enabled 2', true);
        $disabled = mockCollector('disabled', 'Disabled', false);

        $this->registry
            ->register($enabled1)
            ->register($enabled2)
            ->register($disabled);

        $enabledCollectors = $this->registry->enabled();

        expect($enabledCollectors)->toHaveCount(2);
        expect($enabledCollectors->has('enabled1'))->toBeTrue();
        expect($enabledCollectors->has('enabled2'))->toBeTrue();
        expect($enabledCollectors->has('disabled'))->toBeFalse();
    });

    it('returns empty collection when no collectors enabled', function () {
        $disabled = mockCollector('disabled', 'Disabled', false);
        $this->registry->register($disabled);

        $enabled = $this->registry->enabled();

        expect($enabled)->toHaveCount(0);
    });
});

describe('CollectorRegistry Lifecycle', function () {
    it('starts all enabled collectors', function () {
        $enabled = mockCollector('enabled', 'Enabled', true);
        $disabled = mockCollector('disabled', 'Disabled', false);

        $enabled->shouldReceive('start')->once();
        $disabled->shouldReceive('start')->never();

        $this->registry->register($enabled)->register($disabled);
        $this->registry->startAll();
    });

    it('collects data from all enabled collectors', function () {
        $enabled = mockCollector('enabled', 'Enabled', true);
        $disabled = mockCollector('disabled', 'Disabled', false);

        $enabled->shouldReceive('collect')->once();
        $disabled->shouldReceive('collect')->never();

        $this->registry->register($enabled)->register($disabled);
        $this->registry->collectAll();
    });

    it('resets all collectors including disabled ones', function () {
        $enabled = mockCollector('enabled', 'Enabled', true);
        $disabled = mockCollector('disabled', 'Disabled', false);

        $enabled->shouldReceive('reset')->once();
        $disabled->shouldReceive('reset')->once();

        $this->registry->register($enabled)->register($disabled);
        $this->registry->resetAll();
    });
});

describe('CollectorRegistry Data Aggregation', function () {
    it('aggregates data from all enabled collectors', function () {
        $collector1 = mockCollector('test1', 'Test 1', true);
        $collector2 = mockCollector('test2', 'Test 2', true);

        $collector1->shouldReceive('getData')->andReturn(['data' => 'from test1']);
        $collector2->shouldReceive('getData')->andReturn(['data' => 'from test2']);

        $this->registry->register($collector1)->register($collector2);

        $allData = $this->registry->getAllData();

        expect($allData)->toHaveKey('test1');
        expect($allData)->toHaveKey('test2');
        expect($allData['test1'])->toBe(['data' => 'from test1']);
        expect($allData['test2'])->toBe(['data' => 'from test2']);
    });

    it('excludes disabled collectors from data aggregation', function () {
        $enabled = mockCollector('enabled', 'Enabled', true);
        $disabled = mockCollector('disabled', 'Disabled', false);

        $enabled->shouldReceive('getData')->andReturn(['data' => 'from enabled']);
        $disabled->shouldReceive('getData')->never();

        $this->registry->register($enabled)->register($disabled);

        $allData = $this->registry->getAllData();

        expect($allData)->toHaveKey('enabled');
        expect($allData)->not->toHaveKey('disabled');
    });

    it('returns empty array when no enabled collectors', function () {
        $disabled = mockCollector('disabled', 'Disabled', false);
        $this->registry->register($disabled);

        $allData = $this->registry->getAllData();

        expect($allData)->toBeEmpty();
    });
});

describe('CollectorRegistry Edge Cases', function () {
    it('handles empty registry gracefully', function () {
        expect($this->registry->count())->toBe(0);
        expect($this->registry->all())->toHaveCount(0);
        expect($this->registry->enabled())->toHaveCount(0);
        expect($this->registry->getAllData())->toBeEmpty();
    });

    it('handles startAll with no collectors', function () {
        expect(fn() => $this->registry->startAll())->not->toThrow(Exception::class);
    });

    it('handles collectAll with no collectors', function () {
        expect(fn() => $this->registry->collectAll())->not->toThrow(Exception::class);
    });

    it('handles resetAll with no collectors', function () {
        expect(fn() => $this->registry->resetAll())->not->toThrow(Exception::class);
    });
});

// Helper function to create mock collectors
function mockCollector(string $name, string $label, bool $enabled = true): Mockery\MockInterface
{
    $collector = Mockery::mock(CollectorInterface::class);

    $collector->shouldReceive('getName')->andReturn($name);
    $collector->shouldReceive('getLabel')->andReturn($label);
    $collector->shouldReceive('isEnabled')->andReturn($enabled);

    return $collector;
}



