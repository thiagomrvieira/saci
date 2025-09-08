<link rel="stylesheet" href="{{ asset('vendor/saci/css/saci.css') }}">
<script defer src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js"></script>

@php
    $theme = \ThiagoVieira\Saci\SaciConfig::getTheme();
@endphp

<div
    id="saci"
    x-data="{collapsed:false,init(){try{this.collapsed=localStorage.getItem('saci.collapsed')==='1'}catch(e){}},toggle(){this.collapsed=!this.collapsed;try{localStorage.setItem('saci.collapsed',this.collapsed?'1':'0')}catch(e){}},expandAll(){window.dispatchEvent(new CustomEvent('saci-expand-all'))},collapseAll(){window.dispatchEvent(new CustomEvent('saci-collapse-all'))}}"
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
        <ul style="margin: 0; padding: 0; list-style: none;">
            @foreach($templates as $template)
                @include('saci::partials.template-card', ['template' => $template])
            @endforeach
        </ul>
    </div>

    @include('saci::partials.scripts')
</div>