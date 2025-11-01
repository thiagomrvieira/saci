<?php

namespace ThiagoVieira\Saci\Support;

/**
 * Resolves and normalizes file paths.
 *
 * Responsibilities:
 * - Convert absolute paths to project-relative paths
 * - Resolve compiled Blade files to their sources
 * - Identify userland files (non-vendor)
 */
class FilePathResolver
{
    /** @var array<string,string> Cache for blade source resolution */
    protected array $bladeSourceCache = [];

    /** @var string|null Cached base path */
    protected ?string $cachedBasePath = null;

    /**
     * Convert absolute path to project-relative path.
     */
    public function toRelative(string $absolutePath): string
    {
        if ($this->cachedBasePath === null) {
            $this->cachedBasePath = rtrim((string) base_path(), '/');
        }

        $normalized = str_replace('\\', '/', $absolutePath);

        if (!str_starts_with($normalized, $this->cachedBasePath . '/')) {
            return $normalized;
        }

        $relative = substr($normalized, strlen($this->cachedBasePath));
        return $relative === '' ? $normalized : $relative;
    }

    /**
     * Check if file is userland (not vendor or Saci).
     */
    public function isUserlandFile(string $normalizedPath): bool
    {
        return !str_contains($normalizedPath, '/vendor/')
            && !str_contains($normalizedPath, '/ThiagoVieira/Saci/');
    }

    /**
     * Resolve compiled Blade file to original source (with caching).
     *
     * @param string $compiledPath Path to compiled view file
     * @return string|null Original Blade source path or null if not found
     */
    public function resolveBladeSource(string $compiledPath): ?string
    {
        // Check cache first
        if (isset($this->bladeSourceCache[$compiledPath])) {
            return $this->bladeSourceCache[$compiledPath];
        }

        try {
            if (!is_file($compiledPath)) {
                return $this->bladeSourceCache[$compiledPath] = null;
            }

            $content = $this->readFileHead($compiledPath, 4096);
            if (!$content) {
                return $this->bladeSourceCache[$compiledPath] = null;
            }

            // Laravel includes original path in compiled file comment
            // Pattern: /* /full/path/resources/views/...blade.php */
            if (preg_match('#(/.*?/resources/views/[^\n\r*]+?\.blade\.php)#', $content, $matches)) {
                $resolved = str_replace('\\\\', '/', $matches[1]);
                return $this->bladeSourceCache[$compiledPath] = $resolved;
            }
        } catch (\Throwable $e) {
            return $this->bladeSourceCache[$compiledPath] = null;
        }

        return $this->bladeSourceCache[$compiledPath] = null;
    }

    /**
     * Read first N bytes of file safely.
     */
    protected function readFileHead(string $path, int $bytes): ?string
    {
        $fp = @fopen($path, 'r');
        if (!$fp) {
            return null;
        }

        $content = @fread($fp, $bytes);
        @fclose($fp);

        return $content ?: null;
    }
}

