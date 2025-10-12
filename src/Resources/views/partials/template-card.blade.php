<li>
    @php
        $headerRight = '<span class="saci-badge saci-badge-vars">'.count($template['data']).' vars</span>';
        if(isset($template['duration'])) { $headerRight .= ' <span class="saci-badge saci-badge-danger saci-badge-ms">'.$template['duration'].'ms</span>'; }
    @endphp
    @include('saci::partials.card', [
        'key' => $template['path'],
        'title' => $template['path'],
        'headerRight' => $headerRight,
        'open' => false,
        'content' => view('saci::partials.variables-table', ['data' => $template['data']])->render(),
    ])
</li>

