<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Collectors\BaseCollector;

beforeEach(function () {
    // Create a concrete implementation of BaseCollector for testing
    $this->collector = new class extends BaseCollector {
        public $startCalled = false;
        public $collectCalled = false;
        public $resetCalled = false;

        public function getName(): string
        {
            return 'test';
        }

        public function getLabel(): string
        {
            return 'Test Collector';
        }

        protected function doStart(): void
        {
            $this->startCalled = true;
        }

        protected function doCollect(): void
        {
            $this->collectCalled = true;
            $this->data = ['collected' => true];
        }

        protected function doReset(): void
        {
            $this->resetCalled = true;
        }

        // Expose protected properties for testing
        public function isCollectingState(): bool
        {
            return $this->isCollecting;
        }
    };
});

describe('BaseCollector Configuration', function () {
    it('returns correct name', function () {
        expect($this->collector->getName())->toBe('test');
    });

    it('returns correct label', function () {
        expect($this->collector->getLabel())->toBe('Test Collector');
    });

    it('is enabled by default', function () {
        expect($this->collector->isEnabled())->toBeTrue();
    });

    it('can be disabled via config', function () {
        config()->set('saci.collectors.test', false);

        expect($this->collector->isEnabled())->toBeFalse();
    });

    it('checks config key based on name', function () {
        config()->set('saci.collectors.test', false);

        expect($this->collector->isEnabled())->toBeFalse();
    });

    it('uses true as default when config explicitly true', function () {
        config()->set('saci.collectors.test', true);

        expect($this->collector->isEnabled())->toBeTrue();
    });
});

describe('BaseCollector Start Lifecycle', function () {
    it('starts collection when enabled', function () {
        config()->set('saci.collectors.test', true);

        $this->collector->start();

        expect($this->collector->isCollectingState())->toBeTrue();
        expect($this->collector->startCalled)->toBeTrue();
    });

    it('does not start when disabled', function () {
        config()->set('saci.collectors.test', false);

        $this->collector->start();

        expect($this->collector->isCollectingState())->toBeFalse();
        expect($this->collector->startCalled)->toBeFalse();
    });

    it('clears data on start', function () {
        // Simulate previous data
        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData())->not->toBeEmpty();

        // Start again
        $this->collector->start();

        expect($this->collector->getData())->toBeEmpty();
    });

    it('calls doStart hook', function () {
        $this->collector->start();

        expect($this->collector->startCalled)->toBeTrue();
    });
});

describe('BaseCollector Collect Lifecycle', function () {
    it('collects data when collecting is active', function () {
        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->collectCalled)->toBeTrue();
        expect($this->collector->getData())->toBe(['collected' => true]);
    });

    it('does not collect when not started', function () {
        $this->collector->collect();

        expect($this->collector->collectCalled)->toBeFalse();
        expect($this->collector->getData())->toBeEmpty();
    });

    it('does not collect when disabled', function () {
        config()->set('saci.collectors.test', false);

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->collectCalled)->toBeFalse();
    });

    it('calls doCollect hook', function () {
        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->collectCalled)->toBeTrue();
    });
});

describe('BaseCollector Reset Lifecycle', function () {
    it('resets collecting state', function () {
        $this->collector->start();

        expect($this->collector->isCollectingState())->toBeTrue();

        $this->collector->reset();

        expect($this->collector->isCollectingState())->toBeFalse();
    });

    it('clears collected data', function () {
        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData())->not->toBeEmpty();

        $this->collector->reset();

        expect($this->collector->getData())->toBeEmpty();
    });

    it('calls doReset hook', function () {
        $this->collector->reset();

        expect($this->collector->resetCalled)->toBeTrue();
    });

    it('allows restart after reset', function () {
        $this->collector->start();
        $this->collector->collect();
        $this->collector->reset();

        $this->collector->start();
        $this->collector->collect();

        expect($this->collector->getData())->toBe(['collected' => true]);
    });
});

describe('BaseCollector Data Management', function () {
    it('returns empty data initially', function () {
        expect($this->collector->getData())->toBeEmpty();
    });

    it('returns collected data', function () {
        $this->collector->start();
        $this->collector->collect();

        $data = $this->collector->getData();

        expect($data)->toBeArray();
        expect($data)->toHaveKey('collected');
        expect($data['collected'])->toBeTrue();
    });

    it('returns consistent data across multiple calls', function () {
        $this->collector->start();
        $this->collector->collect();

        $data1 = $this->collector->getData();
        $data2 = $this->collector->getData();

        expect($data1)->toBe($data2);
    });
});

describe('BaseCollector Template Method Pattern', function () {
    it('executes template method in correct order for start', function () {
        $order = [];

        $collector = new class extends BaseCollector {
            public array $executionOrder = [];

            public function getName(): string { return 'test'; }
            public function getLabel(): string { return 'Test'; }

            protected function doStart(): void
            {
                $this->executionOrder[] = 'doStart';
            }

            public function start(): void
            {
                $this->executionOrder[] = 'before';
                parent::start();
                $this->executionOrder[] = 'after';
            }
        };

        $collector->start();

        expect($collector->executionOrder)->toBe(['before', 'doStart', 'after']);
    });
});

describe('BaseCollector Edge Cases', function () {
    it('handles multiple start calls', function () {
        $this->collector->start();
        $this->collector->start();
        $this->collector->start();

        expect($this->collector->isCollectingState())->toBeTrue();
    });

    it('handles multiple collect calls', function () {
        $this->collector->start();
        $this->collector->collect();
        $this->collector->collect();

        expect($this->collector->getData())->toBe(['collected' => true]);
    });

    it('handles multiple reset calls', function () {
        $this->collector->start();
        $this->collector->reset();
        $this->collector->reset();

        expect($this->collector->isCollectingState())->toBeFalse();
        expect($this->collector->getData())->toBeEmpty();
    });

    it('handles collect before start gracefully', function () {
        expect(fn() => $this->collector->collect())->not->toThrow(Exception::class);
        expect($this->collector->getData())->toBeEmpty();
    });
});

describe('BaseCollector Integration', function () {
    it('follows complete lifecycle correctly', function () {
        // 1. Initial state
        expect($this->collector->isCollectingState())->toBeFalse();
        expect($this->collector->getData())->toBeEmpty();

        // 2. Start
        $this->collector->start();
        expect($this->collector->isCollectingState())->toBeTrue();
        expect($this->collector->startCalled)->toBeTrue();

        // 3. Collect
        $this->collector->collect();
        expect($this->collector->collectCalled)->toBeTrue();
        expect($this->collector->getData())->not->toBeEmpty();

        // 4. Reset
        $this->collector->reset();
        expect($this->collector->isCollectingState())->toBeFalse();
        expect($this->collector->resetCalled)->toBeTrue();
        expect($this->collector->getData())->toBeEmpty();
    });

    it('can run multiple complete cycles', function () {
        // First cycle
        $this->collector->start();
        $this->collector->collect();
        expect($this->collector->getData())->not->toBeEmpty();
        $this->collector->reset();

        // Second cycle
        $this->collector->start();
        $this->collector->collect();
        expect($this->collector->getData())->not->toBeEmpty();
        $this->collector->reset();

        expect($this->collector->getData())->toBeEmpty();
    });
});

describe('BaseCollector Configuration', function () {
    it('skips start when collector is disabled', function () {
        // Disable the collector
        config()->set('saci.collectors.test', false);

        // Create new instance to pick up config
        $collector = new class extends BaseCollector {
            public function getName(): string { return 'test'; }
            public function getLabel(): string { return 'Test'; }
            public bool $startCalled = false;

            protected function doStart(): void {
                // This should NOT be called when disabled
                $this->startCalled = true;
                $this->data['started'] = true;
            }

            protected function doCollect(): void {
                $this->data['collected'] = true;
            }
        };

        // Start should return early due to disabled state
        $collector->start();
        $collector->collect(); // Won't collect because not started

        $data = $collector->getData();
        expect($data)->not->toHaveKey('started');
        expect($data)->not->toHaveKey('collected');
        expect($collector->startCalled)->toBeFalse();

        // Re-enable
        config()->set('saci.collectors.test', true);
    });
});

describe('BaseCollector Empty Hook Coverage', function () {
    it('covers empty hook methods when not overridden', function () {
        // Create a minimal collector that DOES NOT override the hooks
        $minimalCollector = new class extends BaseCollector {
            public function getName(): string { return 'minimal'; }
            public function getLabel(): string { return 'Minimal'; }

            // NOT overriding doStart, doCollect, doReset
            // This will execute the empty implementations in BaseCollector
        };

        // These calls will execute the empty hook methods
        $minimalCollector->start();
        $minimalCollector->collect();
        $minimalCollector->reset();

        // Verify the lifecycle still works even with empty hooks
        expect($minimalCollector->getData())->toBeEmpty();
    });
});

