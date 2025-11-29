<?php

namespace ThiagoVieira\Saci\Tests\Unit;

use ThiagoVieira\Saci\SaciConfig;

describe('SaciConfig', function () {

    // ============================================================================
    // COVERAGE IMPROVEMENTS - Missing Lines
    // ============================================================================

    describe('Allowed Environments', function () {
        it('returns custom environments when configured', function () {
            config(['saci.environments' => ['local', 'staging', 'production']]);

            $environments = SaciConfig::getAllowedEnvironments();

            expect($environments)->toBe(['local', 'staging', 'production']);
        });

        it('returns empty array when explicitly set', function () {
            config(['saci.environments' => []]);

            $environments = SaciConfig::getAllowedEnvironments();

            expect($environments)->toBe([]);
        });
    });

    describe('UI Position', function () {
        it('returns default position when ui settings are empty', function () {
            config(['saci.ui' => []]);

            $position = SaciConfig::getUIPosition();

            expect($position)->toBe('bottom');
        });

        it('returns custom position when configured', function () {
            config(['saci.ui' => ['position' => 'top']]);

            $position = SaciConfig::getUIPosition();

            expect($position)->toBe('top');
        });

        it('returns default position when position key is missing', function () {
            config(['saci.ui' => ['theme' => 'dark']]);

            $position = SaciConfig::getUIPosition();

            expect($position)->toBe('bottom');
        });
    });

    describe('UI Max Height', function () {
        it('returns default max height when ui settings are empty', function () {
            config(['saci.ui' => []]);

            $maxHeight = SaciConfig::getUIMaxHeight();

            expect($maxHeight)->toBe('30vh');
        });

        it('returns custom max height when configured', function () {
            config(['saci.ui' => ['max_height' => '50vh']]);

            $maxHeight = SaciConfig::getUIMaxHeight();

            expect($maxHeight)->toBe('50vh');
        });

        it('returns default max height when max_height key is missing', function () {
            config(['saci.ui' => ['theme' => 'dark']]);

            $maxHeight = SaciConfig::getUIMaxHeight();

            expect($maxHeight)->toBe('30vh');
        });
    });

    describe('Transparency Bounds', function () {
        it('clamps transparency to minimum 0.0', function () {
            config(['saci.ui' => ['transparency' => -0.5]]);

            $transparency = SaciConfig::getTransparency();

            expect($transparency)->toBe(0.0);
        });

        it('clamps transparency to maximum 1.0', function () {
            config(['saci.ui' => ['transparency' => 1.5]]);

            $transparency = SaciConfig::getTransparency();

            expect($transparency)->toBe(1.0);
        });

        it('accepts valid transparency values', function () {
            config(['saci.ui' => ['transparency' => 0.7]]);

            $transparency = SaciConfig::getTransparency();

            expect($transparency)->toBe(0.7);
        });

        it('handles edge case transparency of exactly 0', function () {
            config(['saci.ui' => ['transparency' => 0.0]]);

            $transparency = SaciConfig::getTransparency();

            expect($transparency)->toBe(0.0);
        });

        it('handles edge case transparency of exactly 1', function () {
            config(['saci.ui' => ['transparency' => 1.0]]);

            $transparency = SaciConfig::getTransparency();

            expect($transparency)->toBe(1.0);
        });

        it('clamps very negative transparency values', function () {
            config(['saci.ui' => ['transparency' => -100]]);

            $transparency = SaciConfig::getTransparency();

            expect($transparency)->toBe(0.0);
        });

        it('clamps very high transparency values', function () {
            config(['saci.ui' => ['transparency' => 100]]);

            $transparency = SaciConfig::getTransparency();

            expect($transparency)->toBe(1.0);
        });
    });

    describe('Theme Validation', function () {
        it('returns default theme for invalid value', function () {
            config(['saci.ui' => ['theme' => 'invalid-theme']]);

            $theme = SaciConfig::getTheme();

            expect($theme)->toBe('default');
        });

        it('accepts valid dark theme', function () {
            config(['saci.ui' => ['theme' => 'dark']]);

            $theme = SaciConfig::getTheme();

            expect($theme)->toBe('dark');
        });

        it('accepts valid minimal theme', function () {
            config(['saci.ui' => ['theme' => 'minimal']]);

            $theme = SaciConfig::getTheme();

            expect($theme)->toBe('minimal');
        });

        it('accepts valid default theme', function () {
            config(['saci.ui' => ['theme' => 'default']]);

            $theme = SaciConfig::getTheme();

            expect($theme)->toBe('default');
        });
    });
});

