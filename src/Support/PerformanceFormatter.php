<?php

namespace ThiagoVieira\Saci\Support;

class PerformanceFormatter
{
    /**
     * Format a duration in milliseconds into a human-friendly string.
     * Uses seconds with 2 decimals when >= 1000ms, otherwise milliseconds with 2 decimals.
     */
    public static function formatMs(?float $milliseconds): ?string
    {
        if ($milliseconds === null) {
            return null;
        }

        $ms = (float) $milliseconds;
        return $ms >= 1000
            ? (number_format($ms / 1000, 2) . 's')
            : (number_format($ms, 2) . 'ms');
    }

    /**
     * Classify a duration in milliseconds into status, color, tooltip and css class.
     * Thresholds (ms): <100 instant; <1000 acceptable; <3000 tolerable; <10000 problematic; else bad.
     * @return array{status:string,color:string,tooltip:string,class:string}|null
     */
    public static function classify(?float $milliseconds): ?array
    {
        if ($milliseconds === null) {
            return null;
        }

        $ms = (float) $milliseconds;
        if ($ms < 100) {
            return [
                'status' => 'Instant',
                'color' => '#28a745',
                'tooltip' => 'Excellent: Instant response, ideal UX.',
                'class' => 'saci-rt--instant',
            ];
        }
        if ($ms < 1000) {
            return [
                'status' => 'Acceptable',
                'color' => '#17a2b8',
                'tooltip' => 'Good: Fast response, still fluid for users.',
                'class' => 'saci-rt--acceptable',
            ];
        }
        if ($ms < 3000) {
            return [
                'status' => 'Tolerable',
                'color' => '#ffc107',
                'tooltip' => 'Moderate: Noticeable delay, consider feedback indicators.',
                'class' => 'saci-rt--tolerable',
            ];
        }
        if ($ms < 10000) {
            return [
                'status' => 'Problematic',
                'color' => '#fd7e14',
                'tooltip' => 'Slow: Frustrating delay, optimize or make async.',
                'class' => 'saci-rt--problematic',
            ];
        }

        return [
            'status' => 'Bad',
            'color' => '#dc3545',
            'tooltip' => 'Unacceptable: Very slow, high risk of user abandonment.',
            'class' => 'saci-rt--bad',
        ];
    }

    /**
     * Convenience method combining format and classification.
     * @return array{display:string,status:string,color:string,tooltip:string,class:string}|null
     */
    public static function formatAndClassify(?float $milliseconds): ?array
    {
        if ($milliseconds === null) {
            return null;
        }
        $display = self::formatMs($milliseconds);
        $meta = self::classify($milliseconds) ?? [];
        if (!$display || empty($meta)) {
            return null;
        }
        return [
            'display' => $display,
            'status' => $meta['status'],
            'color' => $meta['color'],
            'tooltip' => $meta['tooltip'],
            'class' => $meta['class'],
        ];
    }

    /**
     * Classify view render time with different thresholds.
     * Thresholds (ms): <50 excellent; <200 good; <500 moderate; <1000 slow; else very-slow.
     * @return array{status:string,color:string,tooltip:string,class:string}|null
     */
    public static function classifyView(?float $milliseconds): ?array
    {
        if ($milliseconds === null) {
            return null;
        }

        $ms = (float) $milliseconds;
        if ($ms < 50) {
            return [
                'status' => 'Excellent',
                'color' => '#28a745',
                'tooltip' => 'Excellent: Instant render, perfect UX',
                'class' => 'saci-vt--excellent',
            ];
        }
        if ($ms < 200) {
            return [
                'status' => 'Good',
                'color' => '#17a2b8',
                'tooltip' => 'Good: Fast render, still fluid',
                'class' => 'saci-vt--good',
            ];
        }
        if ($ms < 500) {
            return [
                'status' => 'Moderate',
                'color' => '#ffc107',
                'tooltip' => 'Moderate: Noticeable delay, consider caching',
                'class' => 'saci-vt--moderate',
            ];
        }
        if ($ms < 1000) {
            return [
                'status' => 'Slow',
                'color' => '#fd7e14',
                'tooltip' => 'Slow: Optimize queries or partial caching',
                'class' => 'saci-vt--slow',
            ];
        }

        return [
            'status' => 'Very slow',
            'color' => '#dc3545',
            'tooltip' => 'Very slow: High UX risk, needs full optimization',
            'class' => 'saci-vt--very-slow',
        ];
    }

    /**
     * Combine view format and classification.
     * @return array{display:string,status:string,color:string,tooltip:string,class:string}|null
     */
    public static function formatAndClassifyView(?float $milliseconds): ?array
    {
        if ($milliseconds === null) {
            return null;
        }
        $display = self::formatMs($milliseconds);
        $meta = self::classifyView($milliseconds) ?? [];
        if (!$display || empty($meta)) {
            return null;
        }
        return [
            'display' => $display,
            'status' => $meta['status'],
            'color' => $meta['color'],
            'tooltip' => $meta['tooltip'],
            'class' => $meta['class'],
        ];
    }
}


