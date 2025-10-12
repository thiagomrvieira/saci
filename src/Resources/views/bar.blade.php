@php
    $publishedCssPath = public_path('vendor/saci/css/saci.css');
@endphp
@if(file_exists($publishedCssPath))
    <link rel="stylesheet" href="{{ asset('vendor/saci/css/saci.css') }}">
@elseif(!empty($inlineCss ?? null))
    <style>{!! $inlineCss !!}</style>
@endif
<script defer src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js"></script>

@php
    $theme = \ThiagoVieira\Saci\SaciConfig::getTheme();
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
    ])

    <div
        id="saci-content"
        x-show="!collapsed"
        x-cloak
        style="max-height: calc({{ config('saci.ui.max_height', '30vh') }} - 36px);"
    >
        <div x-show="tab==='views'" x-cloak id="saci-tabpanel-views" role="tabpanel" aria-labelledby="saci-tab-views">
            @php
                $totalDuration = collect($templates)
                    ->filter(fn($t) => isset($t['duration']))
                    ->sum('duration');
            @endphp
            <div class="saci-summary">
                <div class="saci-summary-left">Views loaded: {{ $total }}</div>
                <div class="saci-summary-right">Views loading time: <strong>{{ $totalDuration }}ms</strong></div>
            </div>
            <ul style="margin: 0; padding: 0; list-style: none;">
                @foreach($templates as $template)
                    @include('saci::partials.template-card', ['template' => $template])
                @endforeach
            </ul>
        </div>
        <div x-show="tab==='resources'" x-cloak id="saci-tabpanel-request" role="tabpanel" aria-labelledby="saci-tab-request">
            @php
                $requestDuration = (isset($resources['response']['duration_ms']) && is_numeric($resources['response']['duration_ms']))
                    ? number_format((float)$resources['response']['duration_ms'], 2)
                    : null;
                $method = $resources['request']['method'] ?? null;
                $uri = $resources['route']['uri'] ?? null;
            @endphp
            <div class="saci-summary">
                <div class="saci-summary-left">Request: {{ $method }} {{ $uri }}</div>
                @if($requestDuration)
                    <div class="saci-summary-right">Request time: <strong>{{ $requestDuration }}ms</strong></div>
                @endif
            </div>
            @include('saci::partials.resources', ['resources' => $resources ?? []])
        </div>
    </div>

    @include('saci::partials.scripts')
</div>