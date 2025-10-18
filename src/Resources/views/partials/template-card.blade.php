<li>
    @php
        $headerRight = '<span class="saci-badge saci-badge-vars">'.count($template['data']).' vars</span>';
        if (isset($template['duration'])) {
            $viewMeta = \ThiagoVieira\Saci\Support\PerformanceFormatter::formatAndClassifyView((float) $template['duration']);
            if ($viewMeta) {
                $headerRight .= ' <span class="saci-badge saci-badge-danger saci-badge-ms ' . $viewMeta['class'] . '" '
                    . '@mouseenter="showTooltip($event, \'' . e((string) $viewMeta['tooltip']) . '\')" '
                    . '@mouseleave="hideTooltip()" '
                    . '@focus="showTooltip($event, \'' . e((string) $viewMeta['tooltip']) . '\')" '
                    . '@blur="hideTooltip()"'
                    . '>' . e((string) $viewMeta['display']) . '</span>';
            }
        }
    @endphp
    @include('saci::partials.card', [
        'key' => $template['path'],
        'title' => $template['path'],
        'headerRight' => $headerRight,
        'open' => false,
        'content' => view('saci::partials.variables-table', ['data' => $template['data'], 'requestId' => $requestId ?? null])->render(),
    ])
</li>

