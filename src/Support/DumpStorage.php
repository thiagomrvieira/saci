<?php

namespace ThiagoVieira\Saci\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DumpStorage
{
    protected string $diskName;
    protected int $perRequestByteCap;
    protected int $ttlSeconds;

    public function __construct(string $diskName = 'local', int $perRequestByteCap = 1048576, int $ttlSeconds = 60)
    {
        $this->diskName = $diskName;
        $this->perRequestByteCap = $perRequestByteCap; // 1 MB default per request
        $this->ttlSeconds = $ttlSeconds; // cleanup threshold
    }

    public function generateRequestId(): string
    {
        return Str::uuid()->toString();
    }

    public function generateDumpId(): string
    {
        return Str::random(12);
    }

    public function basePath(string $requestId): string
    {
        return 'saci/dumps/' . $requestId;
    }

    public function storeHtml(string $requestId, string $dumpId, string $html): bool
    {
        $disk = Storage::disk($this->diskName);
        $dir = $this->basePath($requestId);
        $this->ensureDirectory($disk, $dir);

        // Enforce per-request cap
        $current = $this->directorySize($disk, $dir);
        $incoming = strlen($html);
        if ($current + $incoming > $this->perRequestByteCap) {
            return false;
        }

        return (bool) $disk->put($dir . '/' . $dumpId . '.html', $html, 'private');
    }

    public function getHtml(string $requestId, string $dumpId): ?string
    {
        $disk = Storage::disk($this->diskName);
        $path = $this->basePath($requestId) . '/' . $dumpId . '.html';
        if (!$disk->exists($path)) {
            return null;
        }
        return $disk->get($path);
    }

    public function cleanupExpired(): void
    {
        $disk = Storage::disk($this->diskName);
        $base = 'saci/dumps';
        if (method_exists($disk, 'allDirectories')) {
            foreach ($disk->allDirectories($base) as $dir) {
                $this->cleanupDir($disk, $dir);
            }
        }
    }

    protected function cleanupDir($disk, string $dir): void
    {
        $now = time();
        if (method_exists($disk, 'files')) {
            foreach ($disk->files($dir) as $file) {
                $ts = $this->mtime($disk, $file);
                if ($ts && ($now - $ts) > $this->ttlSeconds) {
                    $disk->delete($file);
                }
            }
        }
    }

    protected function ensureDirectory($disk, string $dir): void
    {
        if (method_exists($disk, 'exists') && !$disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }
    }

    protected function directorySize($disk, string $dir): int
    {
        $size = 0;
        if (method_exists($disk, 'files')) {
            foreach ($disk->files($dir) as $file) {
                $size += (int) ($this->filesize($disk, $file) ?? 0);
            }
        }
        return $size;
    }

    protected function mtime($disk, string $path): ?int
    {
        try {
            return method_exists($disk, 'lastModified') ? (int) $disk->lastModified($path) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function filesize($disk, string $path): ?int
    {
        try {
            return method_exists($disk, 'size') ? (int) $disk->size($path) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}


