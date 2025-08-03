<?php

namespace ThiagoVieira\Saci;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use ThiagoVieira\Saci\SaciInfo;

class DebugBarInjector
{
    /**
     * Template tracker instance.
     */
    protected TemplateTracker $tracker;

    /**
     * Create a new debug bar injector instance.
     */
    public function __construct(TemplateTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Inject the debug bar into the response.
     */
    public function inject(Response $response): Response
    {
        $content = $response->getContent();

        if (empty($content)) {
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
                'templates' => $this->tracker->getTemplates(),
                'total' => $this->tracker->getTotal(),
                'version' => SaciInfo::getVersion(),
                'author' => SaciInfo::getAuthor()
            ])->render();
        } catch (\Exception $e) {
            Log::error('Saci view error: ' . $e->getMessage());

            return '<!-- Saci Error: View not loaded -->';
        }
    }
}