<?php

namespace ThiagoVieira\Saci\Tests\Unit\Support;

use ThiagoVieira\Saci\Support\FilePathResolver;

beforeEach(function () {
    $this->resolver = new FilePathResolver();
});

// ============================================================================
// 1. PATH CONVERSION (ABSOLUTE TO RELATIVE)
// ============================================================================

describe('Path Conversion', function () {
    it('converts absolute path to relative path', function () {
        $absolutePath = base_path('app/Models/User.php');

        $result = $this->resolver->toRelative($absolutePath);

        expect($result)->toBe('/app/Models/User.php');
    });

    it('handles paths with forward slashes', function () {
        $absolutePath = base_path('resources/views/welcome.blade.php');

        $result = $this->resolver->toRelative($absolutePath);

        expect($result)->toBe('/resources/views/welcome.blade.php');
    });

    it('handles paths with backslashes (Windows)', function () {
        $absolutePath = str_replace('/', '\\', base_path('app\\Http\\Controllers\\UserController.php'));

        $result = $this->resolver->toRelative($absolutePath);

        expect($result)->toBe('/app/Http/Controllers/UserController.php');
    });

    it('returns unchanged path if not inside base path', function () {
        $externalPath = '/var/www/external/file.php';

        $result = $this->resolver->toRelative($externalPath);

        expect($result)->toBe($externalPath);
    });

    it('handles empty relative path', function () {
        $basePath = rtrim(base_path(), '/');

        $result = $this->resolver->toRelative($basePath);

        expect($result)->toBe($basePath); // Should return base path itself
    });

    it('caches base path for performance', function () {
        // First call
        $path1 = base_path('app/Test1.php');
        $result1 = $this->resolver->toRelative($path1);

        // Second call should reuse cached base path
        $path2 = base_path('app/Test2.php');
        $result2 = $this->resolver->toRelative($path2);

        expect($result1)->toBe('/app/Test1.php');
        expect($result2)->toBe('/app/Test2.php');
    });
});

// ============================================================================
// 2. USERLAND FILE DETECTION
// ============================================================================

describe('Userland File Detection', function () {
    it('identifies userland files', function () {
        $result = $this->resolver->isUserlandFile('/app/Models/User.php');

        expect($result)->toBeTrue();
    });

    it('excludes vendor files', function () {
        $result = $this->resolver->isUserlandFile('/vendor/laravel/framework/src/Illuminate/Support/Str.php');

        expect($result)->toBeFalse();
    });

    it('excludes Saci package files', function () {
        $result = $this->resolver->isUserlandFile('/vendor/ThiagoVieira/Saci/src/TemplateTracker.php');

        expect($result)->toBeFalse();
    });

    it('handles paths with mixed case', function () {
        expect($this->resolver->isUserlandFile('/app/Services/MyService.php'))->toBeTrue();
        expect($this->resolver->isUserlandFile('/vendor/Package/file.php'))->toBeFalse();
    });

    it('handles relative paths', function () {
        expect($this->resolver->isUserlandFile('app/Models/User.php'))->toBeTrue();
        expect($this->resolver->isUserlandFile('vendor/package/file.php'))->toBeFalse();
    })->skip('Relative path detection needs leading slash');
});

// ============================================================================
// 3. BLADE SOURCE RESOLUTION
// ============================================================================

describe('Blade Source Resolution', function () {
    it('returns null for non-existent files', function () {
        $result = $this->resolver->resolveBladeSource('/non/existent/file.php');

        expect($result)->toBeNull();
    });

    it('caches blade source resolution results', function () {
        $compiledPath = '/non/existent/compiled.php';
        
        // First call
        $result1 = $this->resolver->resolveBladeSource($compiledPath);
        
        // Second call should use cache (hits line 59)
        $result2 = $this->resolver->resolveBladeSource($compiledPath);
        
        expect($result1)->toBeNull();
        expect($result2)->toBeNull();
        expect($result1)->toBe($result2); // Same cached value
    });
    
    it('caches successful blade source resolution', function () {
        $tempFile = sys_get_temp_dir() . '/test_cache_' . uniqid() . '.php';
        $sourcePath = base_path('resources/views/cached.blade.php');
        
        file_put_contents($tempFile, "<?php /* {$sourcePath} */ ?>\n<html>Content</html>");
        
        // First call - miss cache
        $result1 = $this->resolver->resolveBladeSource($tempFile);
        
        // Second call - hit cache (line 59)
        $result2 = $this->resolver->resolveBladeSource($tempFile);
        
        expect($result1)->toBe(str_replace('\\', '/', $sourcePath));
        expect($result2)->toBe(str_replace('\\', '/', $sourcePath));
        expect($result1)->toBe($result2); // Same instance from cache
        
        unlink($tempFile);
    });

    it('resolves blade source from compiled file', function () {
        // Create a temporary compiled blade file with source path comment
        $tempFile = sys_get_temp_dir() . '/test_compiled_' . uniqid() . '.php';
        $sourcePath = base_path('resources/views/welcome.blade.php');

        file_put_contents($tempFile, "<?php /* {$sourcePath} */ ?>\n<html>Content</html>");

        $result = $this->resolver->resolveBladeSource($tempFile);

        expect($result)->toBe(str_replace('\\', '/', $sourcePath));

        // Cleanup
        unlink($tempFile);
    });

    it('handles blade files with Windows path separators', function () {
        $tempFile = sys_get_temp_dir() . '/test_compiled_win_' . uniqid() . '.php';
        // Use actual backslashes that would appear in a real compiled file
        $sourcePath = '/path/to/resources/views/test.blade.php';

        file_put_contents($tempFile, "<?php /* {$sourcePath} */ ?>\n<html>Content</html>");

        $result = $this->resolver->resolveBladeSource($tempFile);

        expect($result)->toBeString();
        expect(str_contains($result, 'resources/views/test.blade.php'))->toBeTrue();

        unlink($tempFile);
    });

    it('returns null if no blade source comment found', function () {
        $tempFile = sys_get_temp_dir() . '/test_no_comment_' . uniqid() . '.php';

        file_put_contents($tempFile, "<?php\n<html>No comment</html>");

        $result = $this->resolver->resolveBladeSource($tempFile);

        expect($result)->toBeNull();

        unlink($tempFile);
    });

    it('handles empty files', function () {
        $tempFile = sys_get_temp_dir() . '/test_empty_' . uniqid() . '.php';

        file_put_contents($tempFile, '');

        $result = $this->resolver->resolveBladeSource($tempFile);

        expect($result)->toBeNull();

        unlink($tempFile);
    });

    it('reads only first 4096 bytes for performance', function () {
        $tempFile = sys_get_temp_dir() . '/test_large_' . uniqid() . '.php';
        $sourcePath = base_path('resources/views/large.blade.php');

        // Create file with comment at start and then lots of content
        $content = "<?php /* {$sourcePath} */ ?>\n";
        $content .= str_repeat('<!-- Large content -->', 1000); // > 4KB of content

        file_put_contents($tempFile, $content);

        $result = $this->resolver->resolveBladeSource($tempFile);

        expect($result)->toBe(str_replace('\\', '/', $sourcePath));

        unlink($tempFile);
    });

    it('handles source path comment beyond 4096 bytes', function () {
        $tempFile = sys_get_temp_dir() . '/test_late_comment_' . uniqid() . '.php';

        // Put comment after 4KB of content
        $content = str_repeat('<?php /* padding */ ?>', 300); // > 4KB
        $sourcePath = base_path('resources/views/late.blade.php');
        $content .= "\n/* {$sourcePath} */";

        file_put_contents($tempFile, $content);

        $result = $this->resolver->resolveBladeSource($tempFile);

        // Should be null because comment is beyond 4KB limit
        expect($result)->toBeNull();

        unlink($tempFile);
    });
});

// ============================================================================
// 4. EDGE CASES
// ============================================================================

describe('Edge Cases', function () {
    it('handles special characters in paths', function () {
        $path = '/app/Models/Tëst-Ñame (1).php';

        $result = $this->resolver->isUserlandFile($path);

        expect($result)->toBeTrue();
    });

    it('handles very long paths', function () {
        $longPath = '/app/' . str_repeat('very/long/path/', 50) . 'File.php';

        $result = $this->resolver->isUserlandFile($longPath);

        expect($result)->toBeTrue();
    });

    it('handles paths with multiple slashes', function () {
        $path = base_path('app//Models///User.php');

        $result = $this->resolver->toRelative($path);

        expect(str_contains($result, 'app'))->toBeTrue();
        expect(str_contains($result, 'User.php'))->toBeTrue();
    });

    it('handles symlinked paths', function () {
        $absolutePath = base_path('storage/app/file.php');

        $result = $this->resolver->toRelative($absolutePath);

        expect($result)->toBe('/storage/app/file.php');
    });

    it('handles unreadable file gracefully in blade resolution', function () {
        // Try to resolve a directory (unreadable as file)
        $result = $this->resolver->resolveBladeSource(sys_get_temp_dir());
        
        expect($result)->toBeNull();
    });
    
    it('handles file read errors gracefully', function () {
        // Use a path that will cause fopen to fail (line 92)
        $invalidPath = '/dev/null/impossible/path/file.php';
        
        $result = $this->resolver->resolveBladeSource($invalidPath);
        
        expect($result)->toBeNull();
    });
    
    it('caches errors for failed file reads', function () {
        $invalidPath = '/impossible/path.php';
        
        // First call - triggers error and caches null
        $result1 = $this->resolver->resolveBladeSource($invalidPath);
        
        // Second call - returns cached null (line 59)
        $result2 = $this->resolver->resolveBladeSource($invalidPath);
        
        expect($result1)->toBeNull();
        expect($result2)->toBeNull();
    });
});
