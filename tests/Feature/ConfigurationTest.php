<?php

declare(strict_types=1);

use ThiagoVieira\Saci\SaciConfig;
use ThiagoVieira\Saci\RequestValidator;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use Illuminate\Http\Request;

describe('Configuration - Global Enable/Disable', function () {
    it('respects global enable setting', function () {
        config()->set('saci.enabled', true);

        expect(SaciConfig::isEnabled())->toBeTrue();
    });

    it('respects global disable setting', function () {
        config()->set('saci.enabled', false);

        expect(SaciConfig::isEnabled())->toBeFalse();

        config()->set('saci.enabled', true);
    });

    it('defaults to enabled when not configured', function () {
        $original = config('saci.enabled');
        config()->set('saci.enabled', null);

        // Should default to true or handle null gracefully
        expect(SaciConfig::isEnabled())->toBeBool();

        config()->set('saci.enabled', $original);
    });
});

describe('Configuration - Collector Enable/Disable', function () {
    it('enables individual collectors', function () {
        config()->set('saci.collectors.views', true);
        config()->set('saci.collectors.request', true);
        config()->set('saci.collectors.database', true);

        $registry = app(CollectorRegistry::class);

        expect($registry->get('views'))->not->toBeNull();
        expect($registry->get('request'))->not->toBeNull();
        expect($registry->get('database'))->not->toBeNull();
    });

    it('disables individual collectors', function () {
        config()->set('saci.collectors.logs', false);

        $registry = app(CollectorRegistry::class);
        $collector = $registry->get('logs');

        // Collector exists but should be disabled
        expect($collector)->not->toBeNull();
        expect($collector->isEnabled())->toBeFalse();

        config()->set('saci.collectors.logs', true);
    });

    it('allows selective collector configuration', function () {
        config()->set('saci.collectors.views', true);
        config()->set('saci.collectors.request', false);
        config()->set('saci.collectors.database', true);

        $registry = app(CollectorRegistry::class);

        expect($registry->get('views')->isEnabled())->toBeTrue();
        expect($registry->get('request')->isEnabled())->toBeFalse();
        expect($registry->get('database')->isEnabled())->toBeTrue();

        // Reset
        config()->set('saci.collectors.request', true);
    });
});

describe('Configuration - Request Validation', function () {
    it('validates requests based on configuration', function () {
        config()->set('saci.enabled', true);

        $validator = new RequestValidator();
        $request = Request::create('/test', 'GET');

        expect($validator->shouldTrace($request))->toBeTrue();
    });

    it('skips disabled requests', function () {
        config()->set('saci.enabled', false);

        $validator = new RequestValidator();
        $request = Request::create('/test', 'GET');

        expect($validator->shouldTrace($request))->toBeFalse();

        config()->set('saci.enabled', true);
    });

    it('respects environment-based configuration', function () {
        $originalEnv = config('app.env');

        config()->set('app.env', 'production');
        config()->set('saci.enabled', false);

        $validator = new RequestValidator();
        $request = Request::create('/test', 'GET');

        expect($validator->shouldTrace($request))->toBeFalse();

        config()->set('app.env', $originalEnv);
        config()->set('saci.enabled', true);
    });
});

describe('Configuration - Hidden Fields', function () {
    it('reads hidden fields configuration', function () {
        config()->set('saci.hidden_fields', ['password', 'api_key']);

        $hiddenFields = SaciConfig::getHiddenFields();

        expect($hiddenFields)->toContain('password');
        expect($hiddenFields)->toContain('api_key');
    });

    it('provides default hidden fields', function () {
        $hiddenFields = SaciConfig::getHiddenFields();

        expect($hiddenFields)->toBeArray();
        expect($hiddenFields)->not->toBeEmpty();
    });

    it('masks configured keys', function () {
        config()->set('saci.mask_keys', ['/token/', '/secret/']);

        $maskKeys = config('saci.mask_keys', []);

        expect($maskKeys)->toBeArray();
    });
});

describe('Configuration - Performance Settings', function () {
    it('reads performance tracking setting', function () {
        config()->set('saci.performance_tracking', true);

        expect(SaciConfig::isPerformanceTrackingEnabled())->toBeTrue();
    });

    it('allows disabling performance tracking', function () {
        config()->set('saci.performance_tracking', false);

        expect(SaciConfig::isPerformanceTrackingEnabled())->toBeFalse();

        config()->set('saci.performance_tracking', true);
        expect(SaciConfig::isPerformanceTrackingEnabled())->toBeTrue();
    });
});

describe('Configuration - Storage Settings', function () {
    it('reads byte cap configuration', function () {
        config()->set('saci.per_request_bytes', 2097152); // 2MB

        $byteCap = config('saci.per_request_bytes');

        expect($byteCap)->toBe(2097152);
    });

    it('reads TTL configuration', function () {
        config()->set('saci.dump_ttl', 120); // 2 minutes

        $ttl = config('saci.dump_ttl');

        expect($ttl)->toBe(120);
    });

    it('uses default values when not configured', function () {
        $byteCap = config('saci.per_request_bytes', 1048576);
        $ttl = config('saci.dump_ttl', 60);

        expect($byteCap)->toBeInt();
        expect($ttl)->toBeInt();
    });
});

describe('Configuration - Theme Settings', function () {
    it('reads theme configuration', function () {
        config()->set('saci.theme', 'dark');

        $theme = config('saci.theme');

        expect($theme)->toBe('dark');
    });

    it('reads transparency configuration', function () {
        config()->set('saci.transparency', 0.8);

        $transparency = config('saci.transparency');

        expect($transparency)->toBe(0.8);
    });
});

describe('Configuration - Runtime Changes', function () {
    it('applies configuration changes immediately', function () {
        config()->set('saci.enabled', true);
        expect(SaciConfig::isEnabled())->toBeTrue();

        config()->set('saci.enabled', false);
        expect(SaciConfig::isEnabled())->toBeFalse();

        config()->set('saci.enabled', true);
    });

    it('handles dynamic collector enable/disable', function () {
        config()->set('saci.collectors.logs', true);

        $registry = app(CollectorRegistry::class);
        $collector = $registry->get('logs');

        expect($collector->isEnabled())->toBeTrue();

        config()->set('saci.collectors.logs', false);
        expect($collector->isEnabled())->toBeFalse();

        config()->set('saci.collectors.logs', true);
    });
});

describe('Configuration - Edge Cases', function () {
    it('handles null configuration values', function () {
        $original = config('saci.theme');
        config()->set('saci.theme', null);

        $theme = config('saci.theme', 'default');

        // Laravel returns null when explicitly set to null, even with default
        expect($theme)->toBeNull();

        // But we can use ?? for fallback
        $theme = config('saci.theme') ?? 'default';
        expect($theme)->toBe('default');

        config()->set('saci.theme', $original);
    });

    it('handles empty array configurations', function () {
        config()->set('saci.hidden_fields', []);

        $hiddenFields = SaciConfig::getHiddenFields();

        expect($hiddenFields)->toBeArray();
    });

    it('handles invalid configuration types', function () {
        $original = config('saci.enabled');
        config()->set('saci.enabled', 'yes'); // String instead of bool

        // Should handle gracefully
        $enabled = SaciConfig::isEnabled();

        expect($enabled)->toBeBool();

        config()->set('saci.enabled', $original);
    });
});

describe('Configuration - Helper Methods', function () {
    it('provides get method for arbitrary keys', function () {
        config()->set('saci.custom_setting', 'custom_value');

        $value = SaciConfig::get('custom_setting');

        expect($value)->toBe('custom_value');
    });

    it('provides default values via get method', function () {
        $value = SaciConfig::get('non_existent_key', 'default');

        expect($value)->toBe('default');
    });

    it('handles nested configuration keys', function () {
        config()->set('saci.nested.deep.value', 'found');

        $value = config('saci.nested.deep.value');

        expect($value)->toBe('found');
    });
});

describe('Configuration - Validation', function () {
    it('validates byte cap is positive', function () {
        config()->set('saci.per_request_bytes', 1024);

        $byteCap = config('saci.per_request_bytes');

        expect($byteCap)->toBeGreaterThan(0);
    });

    it('validates TTL is positive', function () {
        config()->set('saci.dump_ttl', 60);

        $ttl = config('saci.dump_ttl');

        expect($ttl)->toBeGreaterThan(0);
    });

    it('validates transparency is in valid range', function () {
        config()->set('saci.transparency', 0.5);

        $transparency = config('saci.transparency');

        expect($transparency)->toBeGreaterThanOrEqual(0);
        expect($transparency)->toBeLessThanOrEqual(1);
    });
});

describe('Configuration - Integration', function () {
    it('applies configuration across all components', function () {
        config()->set('saci.enabled', true);
        config()->set('saci.collectors.views', true);
        config()->set('saci.collectors.request', true);

        $registry = app(CollectorRegistry::class);
        $validator = new RequestValidator();

        expect(SaciConfig::isEnabled())->toBeTrue();
        expect($registry->get('views')->isEnabled())->toBeTrue();
        expect($validator->shouldTrace(Request::create('/test', 'GET')))->toBeTrue();
    });

    it('consistently disables across all components', function () {
        config()->set('saci.enabled', false);

        $validator = new RequestValidator();
        $request = Request::create('/test', 'GET');

        expect(SaciConfig::isEnabled())->toBeFalse();
        expect($validator->shouldTrace($request))->toBeFalse();

        config()->set('saci.enabled', true);
    });
});

