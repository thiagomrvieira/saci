<div class="saci-card" data-saci-card-key="{{ $key ?? '' }}">
    <div
        class="saci-card-toggle"
        @if(!empty($title))
            role="button"
            tabindex="0"
            aria-expanded="{{ !empty($open) ? 'true' : 'false' }}"
            @click.stop="toggleCard($el.parentElement)"
            @keydown.enter.prevent="$el.click()"
            @keydown.space.prevent="$el.click()"
        @endif
    >
        @if(!empty($title))
            <span class="saci-path">{{ $title }}</span>
        @endif
        @if(!empty($headerRight))
            <div class="saci-meta">{!! $headerRight !!}</div>
        @endif
    </div>
    <div class="saci-card-content" @if(empty($title)) x-cloak @endif style="display: {{ (!empty($open) || empty($title)) ? 'block' : 'none' }};">
        {!! $content ?? '' !!}
    </div>
</div>


