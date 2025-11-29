<?php

declare(strict_types=1);

use ThiagoVieira\Saci\Support\PerformanceFormatter;

describe('PerformanceFormatter formatMs', function () {
    it('returns null for null input', function () {
        expect(PerformanceFormatter::formatMs(null))->toBeNull();
    });

    it('formats milliseconds below 1000 with ms suffix', function () {
        expect(PerformanceFormatter::formatMs(0))->toBe('0.00ms');
        expect(PerformanceFormatter::formatMs(50))->toBe('50.00ms');
        expect(PerformanceFormatter::formatMs(99.9))->toBe('99.90ms');
        expect(PerformanceFormatter::formatMs(999.99))->toBe('999.99ms');
    });

    it('formats milliseconds at 1000 or above with seconds suffix', function () {
        expect(PerformanceFormatter::formatMs(1000))->toBe('1.00s');
        expect(PerformanceFormatter::formatMs(1500))->toBe('1.50s');
        expect(PerformanceFormatter::formatMs(2000))->toBe('2.00s');
        expect(PerformanceFormatter::formatMs(5432.1))->toBe('5.43s');
    });

    it('formats very large values', function () {
        expect(PerformanceFormatter::formatMs(60000))->toBe('60.00s');
        expect(PerformanceFormatter::formatMs(123456))->toBe('123.46s');
    });

    it('formats very small values', function () {
        expect(PerformanceFormatter::formatMs(0.01))->toBe('0.01ms');
        expect(PerformanceFormatter::formatMs(0.001))->toBe('0.00ms');
    });

    it('handles negative values', function () {
        $result = PerformanceFormatter::formatMs(-10);
        expect($result)->toContain('-10');
        expect($result)->toContain('ms');

        // Negative large values get formatted with thousands separator
        $result = PerformanceFormatter::formatMs(-1500);
        expect($result)->toContain('-1');
        expect($result)->toContain('ms');
    });
});

describe('PerformanceFormatter classify', function () {
    it('returns null for null input', function () {
        expect(PerformanceFormatter::classify(null))->toBeNull();
    });

    it('classifies as Instant for < 100ms', function () {
        $result = PerformanceFormatter::classify(0);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Instant');
        expect($result['color'])->toBe('#28a745');
        expect($result['tooltip'])->toContain('Instant');
        expect($result['class'])->toBe('saci-rt--instant');

        $result = PerformanceFormatter::classify(99.99);
        expect($result['status'])->toBe('Instant');
    });

    it('classifies as Acceptable for 100-999ms', function () {
        $result = PerformanceFormatter::classify(100);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Acceptable');
        expect($result['color'])->toBe('#17a2b8');
        expect($result['tooltip'])->toContain('Fast');
        expect($result['class'])->toBe('saci-rt--acceptable');

        $result = PerformanceFormatter::classify(500);
        expect($result['status'])->toBe('Acceptable');

        $result = PerformanceFormatter::classify(999);
        expect($result['status'])->toBe('Acceptable');
    });

    it('classifies as Tolerable for 1000-2999ms', function () {
        $result = PerformanceFormatter::classify(1000);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Tolerable');
        expect($result['color'])->toBe('#ffc107');
        expect($result['tooltip'])->toContain('Moderate');
        expect($result['class'])->toBe('saci-rt--tolerable');

        $result = PerformanceFormatter::classify(2000);
        expect($result['status'])->toBe('Tolerable');

        $result = PerformanceFormatter::classify(2999);
        expect($result['status'])->toBe('Tolerable');
    });

    it('classifies as Problematic for 3000-9999ms', function () {
        $result = PerformanceFormatter::classify(3000);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Problematic');
        expect($result['color'])->toBe('#fd7e14');
        expect($result['tooltip'])->toContain('Slow');
        expect($result['class'])->toBe('saci-rt--problematic');

        $result = PerformanceFormatter::classify(5000);
        expect($result['status'])->toBe('Problematic');

        $result = PerformanceFormatter::classify(9999);
        expect($result['status'])->toBe('Problematic');
    });

    it('classifies as Bad for >= 10000ms', function () {
        $result = PerformanceFormatter::classify(10000);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Bad');
        expect($result['color'])->toBe('#dc3545');
        expect($result['tooltip'])->toContain('Unacceptable');
        expect($result['class'])->toBe('saci-rt--bad');

        $result = PerformanceFormatter::classify(50000);
        expect($result['status'])->toBe('Bad');
    });

    it('includes all required keys in result', function () {
        $result = PerformanceFormatter::classify(500);

        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('color');
        expect($result)->toHaveKey('tooltip');
        expect($result)->toHaveKey('class');
    });
});

describe('PerformanceFormatter formatAndClassify', function () {
    it('returns null for null input', function () {
        expect(PerformanceFormatter::formatAndClassify(null))->toBeNull();
    });

    it('combines format and classification', function () {
        $result = PerformanceFormatter::formatAndClassify(50);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('display');
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('color');
        expect($result)->toHaveKey('tooltip');
        expect($result)->toHaveKey('class');

        expect($result['display'])->toBe('50.00ms');
        expect($result['status'])->toBe('Instant');
    });

    it('works for various time ranges', function () {
        // Instant (< 100ms)
        $result = PerformanceFormatter::formatAndClassify(25);
        expect($result['display'])->toBe('25.00ms');
        expect($result['status'])->toBe('Instant');

        // Acceptable (100-999ms)
        $result = PerformanceFormatter::formatAndClassify(500);
        expect($result['display'])->toBe('500.00ms');
        expect($result['status'])->toBe('Acceptable');

        // Tolerable (1000-2999ms)
        $result = PerformanceFormatter::formatAndClassify(1500);
        expect($result['display'])->toBe('1.50s');
        expect($result['status'])->toBe('Tolerable');

        // Problematic (3000-9999ms)
        $result = PerformanceFormatter::formatAndClassify(5000);
        expect($result['display'])->toBe('5.00s');
        expect($result['status'])->toBe('Problematic');

        // Bad (>= 10000ms)
        $result = PerformanceFormatter::formatAndClassify(15000);
        expect($result['display'])->toBe('15.00s');
        expect($result['status'])->toBe('Bad');
    });
});

describe('PerformanceFormatter classifyView', function () {
    it('returns null for null input', function () {
        expect(PerformanceFormatter::classifyView(null))->toBeNull();
    });

    it('classifies as Excellent for < 50ms', function () {
        $result = PerformanceFormatter::classifyView(0);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Excellent');
        expect($result['color'])->toBe('#28a745');
        expect($result['tooltip'])->toContain('Excellent');
        expect($result['class'])->toBe('saci-vt--excellent');

        $result = PerformanceFormatter::classifyView(49.9);
        expect($result['status'])->toBe('Excellent');
    });

    it('classifies as Good for 50-199ms', function () {
        $result = PerformanceFormatter::classifyView(50);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Good');
        expect($result['color'])->toBe('#17a2b8');
        expect($result['tooltip'])->toContain('Good');
        expect($result['class'])->toBe('saci-vt--good');

        $result = PerformanceFormatter::classifyView(100);
        expect($result['status'])->toBe('Good');

        $result = PerformanceFormatter::classifyView(199);
        expect($result['status'])->toBe('Good');
    });

    it('classifies as Moderate for 200-499ms', function () {
        $result = PerformanceFormatter::classifyView(200);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Moderate');
        expect($result['color'])->toBe('#ffc107');
        expect($result['tooltip'])->toContain('Moderate');
        expect($result['class'])->toBe('saci-vt--moderate');

        $result = PerformanceFormatter::classifyView(350);
        expect($result['status'])->toBe('Moderate');

        $result = PerformanceFormatter::classifyView(499);
        expect($result['status'])->toBe('Moderate');
    });

    it('classifies as Slow for 500-999ms', function () {
        $result = PerformanceFormatter::classifyView(500);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Slow');
        expect($result['color'])->toBe('#fd7e14');
        expect($result['tooltip'])->toContain('Slow');
        expect($result['class'])->toBe('saci-vt--slow');

        $result = PerformanceFormatter::classifyView(750);
        expect($result['status'])->toBe('Slow');

        $result = PerformanceFormatter::classifyView(999);
        expect($result['status'])->toBe('Slow');
    });

    it('classifies as Very slow for >= 1000ms', function () {
        $result = PerformanceFormatter::classifyView(1000);
        expect($result)->toBeArray();
        expect($result['status'])->toBe('Very slow');
        expect($result['color'])->toBe('#dc3545');
        expect($result['tooltip'])->toContain('Very slow');
        expect($result['class'])->toBe('saci-vt--very-slow');

        $result = PerformanceFormatter::classifyView(5000);
        expect($result['status'])->toBe('Very slow');
    });

    it('includes all required keys in result', function () {
        $result = PerformanceFormatter::classifyView(100);

        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('color');
        expect($result)->toHaveKey('tooltip');
        expect($result)->toHaveKey('class');
    });
});

describe('PerformanceFormatter formatAndClassifyView', function () {
    it('returns null for null input', function () {
        expect(PerformanceFormatter::formatAndClassifyView(null))->toBeNull();
    });

    it('combines format and view classification', function () {
        $result = PerformanceFormatter::formatAndClassifyView(30);

        expect($result)->toBeArray();
        expect($result)->toHaveKey('display');
        expect($result)->toHaveKey('status');
        expect($result)->toHaveKey('color');
        expect($result)->toHaveKey('tooltip');
        expect($result)->toHaveKey('class');

        expect($result['display'])->toBe('30.00ms');
        expect($result['status'])->toBe('Excellent');
    });

    it('works for various view time ranges', function () {
        // Excellent (< 50ms)
        $result = PerformanceFormatter::formatAndClassifyView(25);
        expect($result['display'])->toBe('25.00ms');
        expect($result['status'])->toBe('Excellent');

        // Good (50-199ms)
        $result = PerformanceFormatter::formatAndClassifyView(100);
        expect($result['display'])->toBe('100.00ms');
        expect($result['status'])->toBe('Good');

        // Moderate (200-499ms)
        $result = PerformanceFormatter::formatAndClassifyView(300);
        expect($result['display'])->toBe('300.00ms');
        expect($result['status'])->toBe('Moderate');

        // Slow (500-999ms)
        $result = PerformanceFormatter::formatAndClassifyView(750);
        expect($result['display'])->toBe('750.00ms');
        expect($result['status'])->toBe('Slow');

        // Very slow (>= 1000ms)
        $result = PerformanceFormatter::formatAndClassifyView(2000);
        expect($result['display'])->toBe('2.00s');
        expect($result['status'])->toBe('Very slow');
    });
});

describe('PerformanceFormatter Edge Cases', function () {
    it('handles zero values consistently', function () {
        expect(PerformanceFormatter::formatMs(0))->toBe('0.00ms');
        expect(PerformanceFormatter::classify(0)['status'])->toBe('Instant');
        expect(PerformanceFormatter::classifyView(0)['status'])->toBe('Excellent');
    });

    it('handles boundary values precisely', function () {
        // Request classification boundaries
        expect(PerformanceFormatter::classify(99.99)['status'])->toBe('Instant');
        expect(PerformanceFormatter::classify(100)['status'])->toBe('Acceptable');

        expect(PerformanceFormatter::classify(999.99)['status'])->toBe('Acceptable');
        expect(PerformanceFormatter::classify(1000)['status'])->toBe('Tolerable');

        expect(PerformanceFormatter::classify(2999.99)['status'])->toBe('Tolerable');
        expect(PerformanceFormatter::classify(3000)['status'])->toBe('Problematic');

        expect(PerformanceFormatter::classify(9999.99)['status'])->toBe('Problematic');
        expect(PerformanceFormatter::classify(10000)['status'])->toBe('Bad');

        // View classification boundaries
        expect(PerformanceFormatter::classifyView(49.99)['status'])->toBe('Excellent');
        expect(PerformanceFormatter::classifyView(50)['status'])->toBe('Good');

        expect(PerformanceFormatter::classifyView(199.99)['status'])->toBe('Good');
        expect(PerformanceFormatter::classifyView(200)['status'])->toBe('Moderate');

        expect(PerformanceFormatter::classifyView(499.99)['status'])->toBe('Moderate');
        expect(PerformanceFormatter::classifyView(500)['status'])->toBe('Slow');

        expect(PerformanceFormatter::classifyView(999.99)['status'])->toBe('Slow');
        expect(PerformanceFormatter::classifyView(1000)['status'])->toBe('Very slow');
    });

    it('handles very large values', function () {
        $result = PerformanceFormatter::formatMs(999999);
        expect($result)->toBe('1,000.00s');

        $result = PerformanceFormatter::classify(999999);
        expect($result['status'])->toBe('Bad');

        $result = PerformanceFormatter::classifyView(999999);
        expect($result['status'])->toBe('Very slow');
    });

    it('handles fractional milliseconds', function () {
        expect(PerformanceFormatter::formatMs(1.5))->toBe('1.50ms');
        expect(PerformanceFormatter::formatMs(99.123))->toBe('99.12ms');
        expect(PerformanceFormatter::formatMs(1234.567))->toBe('1.23s');
    });
});

describe('PerformanceFormatter Return Types', function () {
    it('classify returns array with exact structure', function () {
        $result = PerformanceFormatter::classify(100);

        expect($result)->toBeArray();
        expect(array_keys($result))->toBe(['status', 'color', 'tooltip', 'class']);
    });

    it('classifyView returns array with exact structure', function () {
        $result = PerformanceFormatter::classifyView(100);

        expect($result)->toBeArray();
        expect(array_keys($result))->toBe(['status', 'color', 'tooltip', 'class']);
    });

    it('formatAndClassify returns array with exact structure', function () {
        $result = PerformanceFormatter::formatAndClassify(100);

        expect($result)->toBeArray();
        expect(array_keys($result))->toBe(['display', 'status', 'color', 'tooltip', 'class']);
    });

    it('formatAndClassifyView returns array with exact structure', function () {
        $result = PerformanceFormatter::formatAndClassifyView(100);

        expect($result)->toBeArray();
        expect(array_keys($result))->toBe(['display', 'status', 'color', 'tooltip', 'class']);
    });
});

describe('PerformanceFormatter Edge Cases for Null Returns', function () {
    it('formatAndClassify returns null when display is empty', function () {
        // Test line 88: if (!$display || empty($meta)) return null
        // This can happen if formatMs returns null/empty

        // formatMs only returns null for null input (already tested)
        // But we need to test the || empty($meta) condition

        $result = PerformanceFormatter::formatAndClassify(null);
        expect($result)->toBeNull();
    });

    it('formatAndClassifyView returns null when display is empty', function () {
        // Test line 164: if (!$display || empty($meta)) return null

        $result = PerformanceFormatter::formatAndClassifyView(null);
        expect($result)->toBeNull();
    });

    it('formatAndClassify handles extreme negative values', function () {
        // Test with invalid negative value that might cause issues
        $result = PerformanceFormatter::formatAndClassify(-1);

        // Should still return a result (negative values are treated as 0 or small positive)
        expect($result)->toBeArray()
            ->and($result)->toHaveKey('display')
            ->and($result)->toHaveKey('status');
    });

    it('formatAndClassifyView handles extreme negative values', function () {
        // Test with invalid negative value
        $result = PerformanceFormatter::formatAndClassifyView(-1);

        // Should still return a result
        expect($result)->toBeArray()
            ->and($result)->toHaveKey('display')
            ->and($result)->toHaveKey('status');
    });

    it('formatAndClassify handles very large values', function () {
        // Test with extremely large value (line 88 edge case)
        $result = PerformanceFormatter::formatAndClassify(999999999);

        expect($result)->toBeArray();
        expect($result['display'])->toBeString();
        expect($result['status'])->toBe('Bad');
    });

    it('formatAndClassifyView handles very large values', function () {
        // Test with extremely large value (line 164 edge case)
        $result = PerformanceFormatter::formatAndClassifyView(999999999);

        expect($result)->toBeArray();
        expect($result['display'])->toBeString();
        expect($result['status'])->toBe('Very slow');
    });
});

