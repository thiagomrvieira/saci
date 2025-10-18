<?php

namespace ThiagoVieira\Saci;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use ThiagoVieira\Saci\SaciInfo;

class DebugBarInjector
{
    /**
     * Template tracker instance.
     */
    protected TemplateTracker $tracker;
    protected RequestResources $resources;

    /**
     * Cache the inline CSS content to avoid reading the file on every request.
     */
    protected static ?string $cachedInlineCss = null;

    /**
     * Create a new debug bar injector instance.
     */
    public function __construct(TemplateTracker $tracker, RequestResources $resources)
    {
        $this->tracker = $tracker;
        $this->resources = $resources;
    }

    /**
     * Inject the debug bar into the response.
     */
    public function inject(SymfonyResponse $response): SymfonyResponse
    {
        $contentType = $response->headers->get('Content-Type', '');
        $disposition = $response->headers->get('Content-Disposition', '');
        if (stripos($contentType, 'text/html') === false) {
            return $response;
        }
        if (stripos($disposition, 'attachment') !== false) {
            return $response;
        }

        $content = $response->getContent();

        if (empty($content)) {
            return $response;
        }

        // Avoid duplicate injection if the bar already exists
        if (str_contains($content, 'id="saci"') || str_contains($content, '<div id="saci"')) {
            return $response;
        }

        $debugContent = $this->renderDebugBar();

        if (!str_contains($content, '</body>')) {
            $content .= $debugContent;
        } else {
            $content = str_replace('</body>', $debugContent . '</body>', $content);
        }

        $response->setContent($content);

        return $response;
    }

    /**
     * Render the debug bar view.
     */
    protected function renderDebugBar(): string
    {
        try {
            return view('saci::bar', [
                'requestId' => method_exists($this->tracker, 'getRequestId') ? $this->tracker->getRequestId() : null,
                'templates' => $this->tracker->getTemplates(),
                'total' => $this->tracker->getTotal(),
                'version' => SaciInfo::getVersion(),
                'author' => SaciInfo::getAuthor(),
                'inlineCss' => $this->getInlineCss(),
                'inlineJs' => $this->getInlineJs(),
                'resources' => $this->resources->getData(),
            ])->render();
        } catch (\Exception $e) {
            Log::error('Saci view error: ' . $e->getMessage());

            return '<!-- Saci Error: View not loaded -->';
        }
    }

    /**
     * Load CSS content from the package for inline usage when publish is not performed.
     */
    protected function getInlineCss(): ?string
    {
        if (self::$cachedInlineCss !== null) {
            return self::$cachedInlineCss === '' ? null : self::$cachedInlineCss;
        }

        $path = __DIR__ . '/Resources/assets/css/saci.css';
        if (!is_file($path)) {
            self::$cachedInlineCss = '';
            return null;
        }

        try {
            $css = @file_get_contents($path);
            self::$cachedInlineCss = $css !== false ? $css : '';
        } catch (\Throwable $e) {
            self::$cachedInlineCss = '';
        }

        return self::$cachedInlineCss === '' ? null : self::$cachedInlineCss;
    }

    /**
     * Load JS content from the package for inline usage when publish is not performed.
     */
    protected function getInlineJs(): ?string
    {
        static $cachedInlineJs = null;
        if ($cachedInlineJs !== null) {
            return $cachedInlineJs === '' ? null : $cachedInlineJs;
        }

        $path = __DIR__ . '/Resources/assets/js/saci.js';
        if (!is_file($path)) {
            $cachedInlineJs = '';
            return null;
        }

        try {
            $js = @file_get_contents($path);
            $cachedInlineJs = $js !== false ? $js : '';
        } catch (\Throwable $e) {
            $cachedInlineJs = '';
        }

        return $cachedInlineJs === '' ? null : $cachedInlineJs;
    }
}