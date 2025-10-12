<div class="saci-card" data-saci-card-key="{{ $key ?? '' }}">
    <div
        class="saci-card-toggle"
        role="button"
        tabindex="0"
        aria-expanded="{{ !empty($open) ? 'true' : 'false' }}"
        @click.stop="toggleCard($el.parentElement)"
        @keydown.enter.prevent="$el.click()"
        @keydown.space.prevent="$el.click()"
    >
        <span class="saci-path">{{ $title ?? '' }}</span>
        @if(!empty($headerRight))
            <div class="saci-meta">{!! $headerRight !!}</div>
        @endif
    </div>
    <div class="saci-card-content" x-cloak style="display: {{ !empty($open) ? 'block' : 'none' }};">
        {!! $content ?? '' !!}
    </div>
</div>


