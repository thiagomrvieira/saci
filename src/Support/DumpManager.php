<?php

namespace ThiagoVieira\Saci\Support;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Cloner\Data;

class DumpManager
{
    protected VarCloner $cloner;
    protected HtmlDumper $htmlDumper;
    protected DumpStorage $storage;
    protected VarCloner $previewCloner;
    protected CliDumper $cliDumper;

    /** @var array<string,int> */
    protected array $limits;

    public function __construct(DumpStorage $storage, array $limits = [])
    {
        $this->storage = $storage;
        $this->cloner = new VarCloner();
        if (method_exists($this->cloner, 'setMaxItems')) {
            $this->cloner->setMaxItems((int) ($limits['max_items'] ?? 100000));
        }
        if (method_exists($this->cloner, 'setMaxString')) {
            $this->cloner->setMaxString((int) ($limits['max_string'] ?? 100000));
        }

        $this->htmlDumper = new HtmlDumper();
        $this->cliDumper = new CliDumper();
        // Preview cloner with tighter limits
        $this->previewCloner = new VarCloner();
        $previewMaxItems = (int) ($limits['preview_max_items'] ?? 8);
        $previewMaxString = (int) ($limits['preview_max_string'] ?? 80);
        if (method_exists($this->previewCloner, 'setMaxItems')) {
            $this->previewCloner->setMaxItems($previewMaxItems);
        }
        if (method_exists($this->previewCloner, 'setMaxString')) {
            $this->previewCloner->setMaxString($previewMaxString);
        }
        $this->limits = [
            'max_depth' => (int) ($limits['max_depth'] ?? 5),
            'max_items' => (int) ($limits['max_items'] ?? 100),
            'max_string' => (int) ($limits['max_string'] ?? 2000),
            'preview_max_items' => $previewMaxItems,
            'preview_max_string' => $previewMaxString,
            'preview_max_chars' => (int) ($limits['preview_max_chars'] ?? 70),
        ];
    }

    public function clone(mixed $value): Data
    {
        return $this->cloner->cloneVar($value);
    }

    public function clonePreview(mixed $value): Data
    {
        return $this->previewCloner->cloneVar($value);
    }

    /**
     * Render HTML safely (no inline JS) suitable for insertion under CSP with published assets.
     */
    public function renderHtml(Data $data): string
    {
        $output = fopen('php://memory', 'r+');
        $this->htmlDumper->dump($data, $output);
        rewind($output);
        $html = stream_get_contents($output) ?: '';
        fclose($output);
        return $html;
    }

    /**
     * Render HTML with all sections expanded and without inline header assets.
     * This avoids relying on sfdump inline <script>/<style>, which do not execute via innerHTML.
     */
    public function renderHtmlExpanded(Data $data): string
    {
        $html = $this->renderHtml($data);

        // Strip inline <script> and <style> headers added by HtmlDumper
        $html = preg_replace('#<script[\s\S]*?</script>#i', '', $html) ?? $html;
        $html = preg_replace('#<style[\s\S]*?</style>#i', '', $html) ?? $html;

        // Force expanded view for all nodes
        $html = str_replace('sf-dump-compact', 'sf-dump-expanded', $html);

        return $html;
    }

    /**
     * Store a dump for a given request, returning its dump id or null when capped.
     */
    public function storeDump(string $requestId, mixed $value): ?string
    {
        $data = $this->clone($value);
        $html = $this->renderHtmlExpanded($data);
        $dumpId = $this->storage->generateDumpId();
        if (!$this->storage->storeHtml($requestId, $dumpId, $html)) {
            return null;
        }
        return $dumpId;
    }

    /**
     * Build a short, single-line preview string for a value.
     */
    public function buildPreview(mixed $value): string
    {
        $data = $this->clonePreview($value);
        $output = fopen('php://memory', 'r+');
        $this->cliDumper->dump($data, $output);
        rewind($output);
        $text = stream_get_contents($output) ?: '';
        fclose($output);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $charLimit = (int) ($this->limits['preview_max_chars'] ?? 100);
        if (mb_strlen($text) > $charLimit) {
            $text = mb_substr($text, 0, $charLimit) . '…';
        }
        return $text;
    }
}


