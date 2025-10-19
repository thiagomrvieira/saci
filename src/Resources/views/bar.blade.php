@php
    $publishedCssPath = public_path('vendor/saci/css/saci.css');
@endphp

@if(file_exists($publishedCssPath))
    <link rel="stylesheet" href="{{ asset('vendor/saci/css/saci.css') }}?v={{ $version ?? '0' }}">
@elseif(!empty($inlineCss ?? null))
    <style>{!! $inlineCss !!}</style>
@endif

@php $nonce = config('saci.csp_nonce'); @endphp

<script defer src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js" @if($nonce) nonce="{{ $nonce }}" @endif></script>

@php $publishedJsPath = public_path('vendor/saci/js/saci.js'); @endphp

@if(file_exists($publishedJsPath))
    <script defer src="{{ asset('vendor/saci/js/saci.js') }}?v={{ $version ?? '0' }}" @if($nonce) nonce="{{ $nonce }}" @endif></script>
@elseif(!empty($inlineJs ?? null))
    <script @if($nonce) nonce="{{ $nonce }}" @endif>
        {!! $inlineJs !!}
    </script>
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
    if ($trackPerf) {
        $requestDurationMs = (isset($resources['response']['duration_ms']) && is_numeric($resources['response']['duration_ms']))
            ? (float) $resources['response']['duration_ms']
            : null;
        $requestMeta = \ThiagoVieira\Saci\Support\PerformanceFormatter::formatAndClassify($requestDurationMs);
    }
@endphp

<div
    id="saci"
    x-data="saciBar()"
    class="saci-{{ config('saci.ui.position', 'bottom') === 'top' ? 'top' : 'bottom' }} saci-theme-{{ $theme }}"
    :class="{
        'saci-collapsed': collapsed,
        'saci-top': '{{ config('saci.ui.position', 'bottom') }}' === 'top',
        'saci-bottom': '{{ config('saci.ui.position', 'bottom') }}' !== 'top',
        'saci-theme-default': '{{ $theme }}' === 'default',
        'saci-theme-dark': '{{ $theme }}' === 'dark',
        'saci-theme-minimal': '{{ $theme }}' === 'minimal'
    }"
    style="max-height: {{ config('saci.ui.max_height', '30vh') }}; --saci-alpha: {{ \ThiagoVieira\Saci\SaciConfig::getTransparency() }};"
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
    ])

    <div
        id="saci-content"
        x-show="!collapsed"
        x-cloak
        style="max-height: calc({{ config('saci.ui.max_height', '30vh') }} - 36px);"
    >
        <div x-show="tab==='views'" x-cloak id="saci-tabpanel-views" role="tabpanel" aria-labelledby="saci-tab-views">
            <ul style="margin: 0; padding: 0; list-style: none;">
                @foreach($templates as $template)
                    @include('saci::partials.template-card', ['template' => $template])
                @endforeach
            </ul>
        </div>
        <div x-show="tab==='resources'" x-cloak id="saci-tabpanel-request" role="tabpanel" aria-labelledby="saci-tab-request">
            @include('saci::partials.resources', ['resources' => $resources ?? [], 'requestId' => ($requestId ?? null)])
        </div>
        <div x-show="tab==='route'" x-cloak id="saci-tabpanel-route" role="tabpanel" aria-labelledby="saci-tab-route">
            @php $route = $resources['route'] ?? []; @endphp
            @include('saci::partials.card', [
                'key' => 'request-route',
                'title' => '',
                'open' => true,
                'content' => view('saci::partials._request-route', ['route' => $route, 'requestId' => ($requestId ?? null)])->render(),
            ])
        </div>
    </div>

    <!-- Alpine-driven tooltip popover -->
    <template x-teleport="body">
        <div
            id="saci-popover"
            x-show="tooltipOpen"
            :class="tooltipOpen ? 'saci-pop-enter saci-pop-enter-end' : 'saci-pop-leave saci-pop-leave-end'"
            x-transition:enter="saci-pop-enter"
            x-transition:enter-start="saci-pop-enter-start"
            x-transition:enter-end="saci-pop-enter-end"
            x-transition:leave="saci-pop-leave"
            x-transition:leave-start="saci-pop-leave-start"
            x-transition:leave-end="saci-pop-leave-end"
            :style="`left: ${tooltipX}px; top: ${tooltipY}px;`"
            :data-placement="tooltipPlacement"
            role="tooltip"
            aria-live="polite"
        >
            <div x-text="tooltipText"></div>
        </div>
    </template>

    @if(!file_exists($publishedJsPath))

    @endif
</div>