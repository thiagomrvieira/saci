<?php

namespace ThiagoVieira\Saci\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ThiagoVieira\Saci\RequestValidator;

class AssetsController extends Controller
{
    public function __construct(
        protected RequestValidator $validator,
    ) {}

    /**
     * Serve the Saci CSS asset without requiring publish.
     */
    public function css(Request $request): BinaryFileResponse
    {
        $minPath = __DIR__.'/../../Resources/assets/css/saci.min.css';
        $path = __DIR__.'/../../Resources/assets/css/saci.css';
        $useMin = !config('app.debug') && is_file($minPath) && filesize($minPath) > 16;
        return $this->serveFile($request, $useMin ? $minPath : $path, 'text/css; charset=UTF-8');
    }

    /**
     * Serve the Saci JS asset without requiring publish.
     */
    public function js(Request $request): BinaryFileResponse
    {
        $minPath = __DIR__.'/../../Resources/assets/js/saci.min.js';
        $path = __DIR__.'/../../Resources/assets/js/saci.js';
        $useMin = !config('app.debug') && is_file($minPath) && filesize($minPath) > 16;
        return $this->serveFile($request, $useMin ? $minPath : $path, 'application/javascript; charset=UTF-8');
    }

    /**
     * Send a static file with strong cache headers and safety checks.
     */
    protected function serveFile(Request $request, string $path, string $contentType): BinaryFileResponse
    {
        // Only serve when Saci is enabled and client is allowed
        if (!$this->validator->shouldServeAssets($request)) {
            abort(403);
        }

        if (!is_file($path)) {
            abort(404);
        }

        $response = response()->file($path, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            // Long-lived cache; version querystring will bust cache when Saci updates
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);

        // Add Last-Modified only (avoid hashing cost for ETag in production)
        $lastModified = gmdate('D, d M Y H:i:s', filemtime($path)).' GMT';
        $response->headers->set('Last-Modified', $lastModified);

        // Handle conditional requests
        $ifModifiedSince = $request->headers->get('If-Modified-Since');
        if ($ifModifiedSince === $lastModified) {
            $response->setNotModified();
        }

        return $response;
    }
}


