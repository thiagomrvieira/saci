<li class="saci-card">
    <div
        class="saci-card-toggle"
        role="button"
        tabindex="0"
        aria-expanded="false"
        @click.stop="
            const header = $el;
            const container = header.parentElement;
            const contentEl = container.querySelector('.saci-card-content');
            if (!contentEl) return;
            const expanded = header.getAttribute('aria-expanded') === 'true';
            contentEl.style.display = expanded ? 'none' : 'block';
            header.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        "
        @keydown.enter.prevent="$el.click()"
        @keydown.space.prevent="$el.click()"
    >
        <span class="saci-path">{{ $template['path'] }}</span>
        <div class="saci-meta">
            <span class="saci-badge">
                {{ count($template['data']) }} vars
            </span>
            @if(isset($template['duration']))
                <span class="saci-badge saci-badge-danger">
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

