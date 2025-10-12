<li class="saci-card" data-saci-card-key="{{ $template['path'] }}">
    <div
        class="saci-card-toggle"
        role="button"
        tabindex="0"
        aria-expanded="false"
        @click.stop="toggleCard($el.parentElement)"
        @keydown.enter.prevent="$el.click()"
        @keydown.space.prevent="$el.click()"
    >
        <span class="saci-path">{{ $template['path'] }}</span>
        <div class="saci-meta">
            <span class="saci-badge saci-badge-vars">
                {{ count($template['data']) }} vars
            </span>
            @if(isset($template['duration']))
                <span class="saci-badge saci-badge-danger saci-badge-ms">
                    {{ $template['duration'] }}ms
                </span>
            @endif
        </div>
    </div>
    @if(!empty($template['data']))
        <div class="saci-card-content" x-cloak style="display: none;">
            @include('saci::partials.variables-table', ['data' => $template['data']])
        </div>
    @endif
</li>

