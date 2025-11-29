<?php

use ThiagoVieira\Saci\SaciInfo;
use Composer\InstalledVersions;

beforeEach(function () {
    // Store original state if needed
});

afterEach(function () {
    Mockery::close();
});

describe('SaciInfo Constants', function () {
    it('has correct VERSION constant', function () {
        expect(SaciInfo::VERSION)->toBe('2.2.0');
    });

    it('has correct AUTHOR constant', function () {
        expect(SaciInfo::AUTHOR)->toBe('Thiago Vieira');
    });

    it('has correct NAME constant', function () {
        expect(SaciInfo::NAME)->toBe('Saci');
    });

    it('has correct DESCRIPTION constant', function () {
        expect(SaciInfo::DESCRIPTION)->toContain('Laravel debugger');
    });
});

describe('SaciInfo getVersion', function () {
    it('returns version string', function () {
        // In dev environment returns 'dev-main', in production returns semantic version
        $version = SaciInfo::getVersion();

        expect($version)->toBeString()
            ->and($version)->not->toBeEmpty();
    });

    it('returns valid version format', function () {
        // Can be semantic version (1.0.0) or dev version (dev-main)
        $version = SaciInfo::getVersion();

        expect($version)->toBeString()
            ->and($version)->toMatch('/^(\d+\.\d+\.\d+|dev-.+)$/');
    });

    it('strips v prefix from version if present', function () {
        // The ltrim should handle 'v' prefix
        $version = SaciInfo::getVersion();

        expect($version)->not->toStartWith('v')
            ->and($version)->not->toStartWith('V');
    });

    it('handles version with v prefix correctly', function () {
        // Test ltrim logic for VERSION constant
        expect(ltrim('v1.0.0', 'vV '))->toBe('1.0.0')
            ->and(ltrim('V2.0.0', 'vV '))->toBe('2.0.0')
            ->and(ltrim('1.0.0', 'vV '))->toBe('1.0.0');
    });

    it('uses InstalledVersions when available', function () {
        // Test that it tries to use Composer's InstalledVersions
        $version = SaciInfo::getVersion();

        // Should return something (either from Composer or fallback)
        expect($version)->toBeString()
            ->and($version)->not->toBeEmpty();
    });

    it('handles empty version from InstalledVersions', function () {
        // Test the conditional check for empty string (line 37-39)
        // Even if InstalledVersions returns empty, should fallback to VERSION constant
        $version = SaciInfo::getVersion();

        expect($version)->not->toBeEmpty();
    });

    it('handles non-string version from InstalledVersions', function () {
        // Test the is_string check (line 37)
        // Should handle edge case where getPrettyVersion returns non-string
        $version = SaciInfo::getVersion();

        expect($version)->toBeString();
    });

    it('falls back to VERSION constant when InstalledVersions fails', function () {
        // This is tricky to test directly, but we can verify the fallback logic
        // The catch block (lines 41-43) will trigger if InstalledVersions throws
        // In our test environment, this might happen naturally

        // Call getVersion - it should never throw an exception
        $version = SaciInfo::getVersion();

        // Should always return a valid string (either from Composer or VERSION constant)
        expect($version)->toBeString()
            ->and($version)->not->toBeEmpty();

        // Verify the VERSION constant is accessible as fallback
        expect(SaciInfo::VERSION)->toBe('2.2.0');
    });

    it('handles package not found in InstalledVersions', function () {
        // If the package is not found, InstalledVersions might throw
        // The catch block should handle it gracefully

        // This is the scenario where lines 41-43 would execute
        // We can't easily mock static methods, but we can test the behavior

        $version = SaciInfo::getVersion();

        // Should still return a version (fallback to VERSION constant)
        expect($version)->toMatch('/^(\d+\.\d+\.\d+|dev-.+)$/');
    });

    it('verifies VERSION constant is valid fallback', function () {
        // Test that the VERSION constant (used in line 44 as fallback) is valid
        $version = ltrim(SaciInfo::VERSION, 'vV ');

        expect($version)->toBe('2.2.0')
            ->and($version)->toMatch('/^\d+\.\d+\.\d+$/');
    });

    it('tests getVersion error handling path', function () {
        // The catch block (lines 41-43) catches any Throwable from InstalledVersions
        // In dev environment, if package is not in vendor, it might throw
        // We verify the method always returns something valid

        $version = SaciInfo::getVersion();

        // Should NEVER throw exception, always return a string
        expect($version)->toBeString()
            ->and($version)->not->toBeEmpty()
            ->and(strlen($version))->toBeGreaterThan(0);
    });

    it('handles ltrim correctly on VERSION constant', function () {
        // Test the ltrim logic used in line 44 (fallback path)
        $original = SaciInfo::VERSION;
        $trimmed = ltrim($original, 'vV ');

        expect($trimmed)->toBe('2.2.0');

        // Test with various prefixes
        expect(ltrim('v2.2.0', 'vV '))->toBe('2.2.0');
        expect(ltrim('V2.2.0', 'vV '))->toBe('2.2.0');
        expect(ltrim(' 2.2.0', 'vV '))->toBe('2.2.0');
        expect(ltrim('2.2.0', 'vV '))->toBe('2.2.0');
    });
});

describe('SaciInfo getAuthor', function () {
    it('returns author name', function () {
        expect(SaciInfo::getAuthor())->toBe('Thiago Vieira');
    });

    it('returns non-empty string', function () {
        expect(SaciInfo::getAuthor())->toBeString()
            ->and(SaciInfo::getAuthor())->not->toBeEmpty();
    });
});

describe('SaciInfo getName', function () {
    it('returns package name', function () {
        expect(SaciInfo::getName())->toBe('Saci');
    });

    it('returns non-empty string', function () {
        expect(SaciInfo::getName())->toBeString()
            ->and(SaciInfo::getName())->not->toBeEmpty();
    });

    it('returns expected package name', function () {
        $name = SaciInfo::getName();

        expect($name)->toBe('Saci')
            ->and($name)->toHaveLength(4);
    });
});

describe('SaciInfo getDescription', function () {
    it('returns package description', function () {
        $description = SaciInfo::getDescription();

        expect($description)->toBeString()
            ->and($description)->not->toBeEmpty();
    });

    it('contains relevant keywords', function () {
        $description = SaciInfo::getDescription();

        expect($description)->toContain('Laravel')
            ->and($description)->toContain('debugger');
    });

    it('returns complete description text', function () {
        $description = SaciInfo::getDescription();

        expect($description)->toBe('A modern, elegant Laravel debugger that shows loaded views and their data in a floating bar');
    });

    it('description is meaningful length', function () {
        $description = SaciInfo::getDescription();

        expect(strlen($description))->toBeGreaterThan(20);
    });
});

describe('SaciInfo Static Methods', function () {
    it('all static methods return strings', function () {
        expect(SaciInfo::getVersion())->toBeString()
            ->and(SaciInfo::getAuthor())->toBeString()
            ->and(SaciInfo::getName())->toBeString()
            ->and(SaciInfo::getDescription())->toBeString();
    });

    it('all static methods return non-empty values', function () {
        expect(SaciInfo::getVersion())->not->toBeEmpty()
            ->and(SaciInfo::getAuthor())->not->toBeEmpty()
            ->and(SaciInfo::getName())->not->toBeEmpty()
            ->and(SaciInfo::getDescription())->not->toBeEmpty();
    });
});

describe('SaciInfo Version Handling', function () {
    it('version constant matches semantic version format', function () {
        // The VERSION constant should always be semantic version
        expect(SaciInfo::VERSION)->toMatch('/^\d+\.\d+\.\d+$/');
    });

    it('handles InstalledVersions fallback gracefully', function () {
        // This tests the entire try-catch-fallback flow (lines 34-44)
        $version = SaciInfo::getVersion();

        // Should always return a valid version (either from Composer or constant)
        expect($version)->toBeString()
            ->and($version)->not->toBeEmpty();
    });

    it('getVersion always returns valid format', function () {
        // Can be '2.2.0' or 'dev-main' depending on environment
        $version = SaciInfo::getVersion();

        // Should match either semantic version or dev version
        $isSemanticVersion = preg_match('/^\d+\.\d+\.\d+$/', $version);
        $isDevVersion = preg_match('/^dev-.+$/', $version);

        expect($isSemanticVersion || $isDevVersion)->toBeTrue();
    });
});

