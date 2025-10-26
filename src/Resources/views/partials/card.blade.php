<div class="saci-card {{ (!empty($open) || empty($title)) ? 'is-open' : '' }}" data-saci-card-key="{{ $key ?? '' }}">
    <div
        class="saci-card-toggle"
        @if(!empty($title))
            role="button"
            tabindex="0"
            aria-expanded="{{ !empty($open) ? 'true' : 'false' }}"
        @endif
    >
        @if(!empty($title))
            <span class="saci-path">{{ $title }}</span>
        @endif
        @if(!empty($headerRight))
            <div class="saci-meta">{!! $headerRight !!}</div>
        @endif
    </div>
    <div class="saci-card-content" style="display: {{ (!empty($open) || empty($title)) ? 'block' : 'none' }};">
        {!! $content ?? '' !!}
    </div>
</div>


