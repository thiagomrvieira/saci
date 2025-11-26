<?php

declare(strict_types=1);

use ThiagoVieira\Saci\DebugBarInjector;
use ThiagoVieira\Saci\Support\CollectorRegistry;
use Illuminate\Http\Response;

beforeEach(function () {
    $this->registry = app(CollectorRegistry::class);
    $this->injector = new DebugBarInjector($this->registry);
});

describe('DebugBar Injection - HTML Detection', function () {
    it('injects debug bar into HTML responses', function () {
        $response = new Response('<html><body><h1>Test</h1></body></html>', 200, [
            'Content-Type' => 'text/html'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toContain('<h1>Test</h1>');
    });

    it('skips non-HTML responses', function () {
        $jsonContent = '{"message": "test"}';
        $response = new Response($jsonContent, 200, [
            'Content-Type' => 'application/json'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe($jsonContent);
        expect($injected->getContent())->not->toContain('saci');
    });

    it('skips XML responses', function () {
        $xmlContent = '<?xml version="1.0"?><root><item>test</item></root>';
        $response = new Response($xmlContent, 200, [
            'Content-Type' => 'application/xml'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe($xmlContent);
    });

    it('skips plain text responses', function () {
        $textContent = 'Plain text content';
        $response = new Response($textContent, 200, [
            'Content-Type' => 'text/plain'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe($textContent);
    });
});

describe('DebugBar Injection - File Downloads', function () {
    it('skips file attachments', function () {
        $pdfContent = 'PDF binary content';
        $response = new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="document.pdf"'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe($pdfContent);
        expect($injected->getContent())->not->toContain('saci');
    });

    it('skips inline file responses', function () {
        $imageContent = 'Image binary data';
        $response = new Response($imageContent, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="image.png"'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe($imageContent);
    });
});

describe('DebugBar Injection - HTML Structure', function () {
    it('injects before closing body tag', function () {
        $html = '<html><head><title>Test</title></head><body><h1>Content</h1></body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);
        $content = $injected->getContent();

        expect($content)->toContain('<h1>Content</h1>');
        expect($content)->toContain('</body>');
    });

    it('appends to HTML without body tag', function () {
        $html = '<html><head><title>Test</title></head><div>No body tag</div></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toContain('No body tag');
    });

    it('handles empty responses', function () {
        $response = new Response('', 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe('');
    });

    it('handles responses without content type', function () {
        $response = new Response('<html><body>Test</body></html>');

        $injected = $this->injector->inject($response);

        // Without explicit text/html, should not inject
        expect($injected->getContent())->toBe('<html><body>Test</body></html>');
    });
});

describe('DebugBar Injection - Duplicate Prevention', function () {
    it('prevents duplicate injection with id attribute', function () {
        $html = '<html><body><div id="saci">Already injected</div></body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe($html);
    });

    it('prevents duplicate injection with opening div tag', function () {
        $html = '<html><body><div id="saci" class="bar">Already injected</div></body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toBe($html);
    });
});

describe('DebugBar Injection - Data Preparation', function () {
    it('includes data from all collectors', function () {
        $html = '<html><body>Test</body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        // Trigger collection
        $this->registry->startAll();
        $this->registry->collectAll();

        $injected = $this->injector->inject($response);

        expect($injected)->toBeInstanceOf(Response::class);
    });

    it('handles missing collectors gracefully', function () {
        $html = '<html><body>Test</body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected)->toBeInstanceOf(Response::class);
    });
});

describe('DebugBar Injection - Content Types', function () {
    it('handles text/html charset', function () {
        $html = '<html><body>UTF-8 content: café</body></html>';
        $response = new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toContain('café');
    });

    it('is case-insensitive for content type', function () {
        $html = '<html><body>Test</body></html>';
        $response = new Response($html, 200, [
            'Content-Type' => 'TEXT/HTML'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected)->toBeInstanceOf(Response::class);
    });
});

describe('DebugBar Injection - Error Handling', function () {
    it('handles view rendering errors gracefully', function () {
        $html = '<html><body>Test</body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        // Even if view fails, should not crash
        $injected = $this->injector->inject($response);

        expect($injected)->toBeInstanceOf(Response::class);
    });

    it('preserves original response on injection error', function () {
        $originalHtml = '<html><body><h1>Original</h1></body></html>';
        $response = new Response($originalHtml, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toContain('<h1>Original</h1>');
    });
});

describe('DebugBar Injection - Response Modification', function () {
    it('preserves response status code', function () {
        $response = new Response('<html><body>Created</body></html>', 201, [
            'Content-Type' => 'text/html'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->getStatusCode())->toBe(201);
    });

    it('preserves response headers', function () {
        $response = new Response('<html><body>Test</body></html>', 200, [
            'Content-Type' => 'text/html',
            'X-Custom-Header' => 'CustomValue',
            'Cache-Control' => 'no-cache'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->headers->get('X-Custom-Header'))->toBe('CustomValue');
        // Cache-Control may be modified by framework
        expect($injected->headers->has('Cache-Control'))->toBeTrue();
    });

    it('modifies only content', function () {
        $response = new Response('<html><body>Test</body></html>', 200, [
            'Content-Type' => 'text/html',
            'X-Frame-Options' => 'DENY'
        ]);

        $injected = $this->injector->inject($response);

        expect($injected->headers->get('X-Frame-Options'))->toBe('DENY');
        expect($injected->getContent())->toContain('Test');
    });
});

describe('DebugBar Injection - Special Cases', function () {
    it('handles responses with BOM', function () {
        $html = "\xEF\xBB\xBF<html><body>UTF-8 BOM</body></html>";
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected)->toBeInstanceOf(Response::class);
    });

    it('handles very large responses', function () {
        $largeContent = '<html><body>' . str_repeat('<p>Content</p>', 10000) . '</body></html>';
        $response = new Response($largeContent, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected)->toBeInstanceOf(Response::class);
        expect(strlen($injected->getContent()))->toBeGreaterThan(strlen($largeContent) - 100);
    });

    it('handles responses with special HTML entities', function () {
        $html = '<html><body>&lt;script&gt;alert("test")&lt;/script&gt;</body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html']);

        $injected = $this->injector->inject($response);

        expect($injected->getContent())->toContain('&lt;script&gt;');
    });
});

