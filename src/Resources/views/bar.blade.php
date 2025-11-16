@php
    $publishedCssPath = public_path('vendor/saci/css/saci.css');
@endphp

@if(file_exists($publishedCssPath) && !config('saci.force_internal_assets'))
    <link rel="stylesheet" href="{{ asset('vendor/saci/css/saci.css') }}?v={{ $version ?? '0' }}">
@else
    <link rel="stylesheet" href="{{ url('/__saci/assets/css') }}?v={{ $version ?? '0' }}">
@endif

@php $nonce = config('saci.csp_nonce'); @endphp

@php $publishedJsPath = public_path('vendor/saci/js/saci.js'); @endphp

@if(file_exists($publishedJsPath) && !config('saci.force_internal_assets'))
    <script defer src="{{ asset('vendor/saci/js/saci.js') }}?v={{ $version ?? '0' }}" @if($nonce) nonce="{{ $nonce }}" @endif></script>
@else
    <script defer src="{{ url('/__saci/assets/js') }}?v={{ $version ?? '0' }}" @if($nonce) nonce="{{ $nonce }}" @endif></script>
@endif

@php
    $theme = \ThiagoVieira\Saci\SaciConfig::getTheme();
    $trackPerf = \ThiagoVieira\Saci\SaciConfig::isPerformanceTrackingEnabled();
    // Compute summaries for header controls
    $viewsMeta = null;
    if ($trackPerf) {
        $totalDuration = collect($templates)
            ->filter(fn($t) => isset($t['duration']))
            ->sum('duration');
        $viewsMeta = \ThiagoVieira\Saci\Support\PerformanceFormatter::formatAndClassifyView($totalDuration);
    }
    $requestMeta = null;
    $method = $resources['request']['method'] ?? null;
    $uri = $resources['route']['uri'] ?? null;
    $logsCount = count($resources['logs'] ?? []);

    // Database stats
    $databaseData = $resources['database'] ?? [];
    $dbCount = $databaseData['total_queries'] ?? 0;
    $dbTime = $databaseData['total_time'] ?? 0;
    $dbN1Count = count($databaseData['possible_n_plus_one'] ?? []);

    if ($trackPerf) {
        $requestDurationMs = (isset($resources['response']['duration_ms']) && is_numeric($resources['response']['duration_ms']))
            ? (float) $resources['response']['duration_ms']
            : null;
        $requestMeta = \ThiagoVieira\Saci\Support\PerformanceFormatter::formatAndClassify($requestDurationMs);
    }
@endphp

<div
    id="saci"
    class="saci-{{ config('saci.ui.position', 'bottom') === 'top' ? 'top' : 'bottom' }} saci-theme-{{ $theme }} saci-collapsed"
    style="max-height: {{ config('saci.ui.max_height', '30vh') }}; --saci-alpha: {{ \ThiagoVieira\Saci\SaciConfig::getTransparency() }};"
    data-saci-request-id="{{ $requestId ?? '' }}"
    data-views-display="{{ $viewsMeta['display'] ?? '' }}"
    data-views-class="{{ $viewsMeta['class'] ?? '' }}"
    data-views-tooltip="{{ $viewsMeta['tooltip'] ?? '' }}"
    data-request-display="{{ $requestMeta['display'] ?? '' }}"
    data-request-class="{{ $requestMeta['class'] ?? '' }}"
    data-request-tooltip="{{ $requestMeta['tooltip'] ?? '' }}"
    data-total-views="{{ $total }}"
    data-method="{{ $method }}"
    data-uri="{{ $uri }}"
    data-logs-count="{{ $logsCount }}"
    data-db-count="{{ $dbCount }}"
    data-db-time="{{ $dbTime }}"
    data-db-n1="{{ $dbN1Count }}"
>
    @include('saci::partials.header', [
        'version' => $version,
        'author' => $author,
        'total' => $total,
        'templates' => $templates,
        'viewsMeta' => $viewsMeta,
        'method' => $method,
        'uri' => $uri,
        'requestMeta' => $requestMeta,
        'logs' => ($resources['logs'] ?? []),
        'resources' => $resources,
    ])

    <div
        id="saci-content"
        style="max-height: calc({{ config('saci.ui.max_height', '30vh') }} - 36px); display: none;"
    >
        <div id="saci-tabpanel-views" class="saci-panel" role="tabpanel" aria-labelledby="saci-tab-views" style="display: none;">
            <ul style="margin: 0; padding: 0; list-style: none;">
                @foreach($templates as $template)
                    @include('saci::partials.template-card', ['template' => $template])
                @endforeach
            </ul>
        </div>
        <div id="saci-tabpanel-request" class="saci-panel" role="tabpanel" aria-labelledby="saci-tab-request" style="display: none;">
            @include('saci::partials.resources', ['resources' => $resources ?? [], 'requestId' => ($requestId ?? null)])
        </div>
        <div id="saci-tabpanel-route" class="saci-panel" role="tabpanel" aria-labelledby="saci-tab-route" style="display: none;">
            @php $route = $resources['route'] ?? []; @endphp
            @include('saci::partials.card', [
                'key' => 'request-route',
                'title' => '',
                'open' => true,
                'content' => view(
                    'saci::partials._request-route',
                    ['route' => $route, 'requestId' => ($requestId ?? null)]
                )->render(),
            ])
        </div>
        <div id="saci-tabpanel-logs" class="saci-panel" role="tabpanel" aria-labelledby="saci-tab-logs" style="display: none;">
            @include('saci::partials.logs', ['logs' => ($resources['logs'] ?? []), 'requestId' => ($requestId ?? null)])
        </div>
        <div id="saci-tabpanel-database" class="saci-panel" role="tabpanel" aria-labelledby="saci-tab-database" style="display: none;">
            @include('saci::partials.database', [
                'resources' => $resources ?? [],
                'requestId' => ($requestId ?? null)
            ])
        </div>
    </div>
</div>