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
                'resources' => $this->resources->getData(),
            ])->render();
        } catch (\Exception $e) {
            Log::error('Saci view error: ' . $e->getMessage());

            return '<!-- Saci Error: View not loaded -->';
        }
    }


}