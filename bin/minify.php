#!/usr/bin/env php
<?php
// Simple minifier for Saci assets (no external deps). Conservative to avoid breaking JS.

function minify_css(string $css): string {
    // Remove comments
    $css = preg_replace('#/\*[^!*][\s\S]*?\*/#', '', $css) ?? $css; // keep /*! */ if any
    // Collapse whitespace
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;
    // Remove spaces around symbols
    $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css) ?? $css;
    // Trim
    return trim($css);
}

function minify_js_conservative(string $js): string {
    // Remove block comments (but not in strings)
    $js = preg_replace('#/\*[^!*][\s\S]*?\*/#', '', $js) ?? $js; // keep /*! */ if any
    // Collapse runs of whitespace to single space
    $js = preg_replace('/[\t\f\v ]+/', ' ', $js) ?? $js;
    // Collapse multiple newlines
    $js = preg_replace('/\n{2,}/', "\n", $js) ?? $js;
    // Trim lines
    $lines = explode("\n", $js);
    foreach ($lines as &$line) { $line = trim($line); }
    unset($line);
    $js = implode("\n", $lines);
    // Optional: remove spaces before/after common tokens
    $js = preg_replace('/\s*([=+\-*/%<>!?&|,:;{}()\[\]])\s*/', '$1', $js) ?? $js;
    return trim($js);
}

function write_min(string $src, string $dest, callable $fn): void {
    if (!is_file($src)) return;
    $in = file_get_contents($src);
    if ($in === false) return;
    $out = $fn($in);
    if ($out === '') return;
    if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0777, true);
    file_put_contents($dest, $out);
}

$root = __DIR__ . '/../src/Resources/assets';

// CSS
$cssSrc = $root . '/css/saci.css';
$cssMin = $root . '/css/saci.min.css';
write_min($cssSrc, $cssMin, 'minify_css');

// JS
$jsSrc = $root . '/js/saci.js';
$jsMin = $root . '/js/saci.min.js';
write_min($jsSrc, $jsMin, 'minify_js_conservative');

echo "Minified assets written to:\n- $cssMin\n- $jsMin\n";



