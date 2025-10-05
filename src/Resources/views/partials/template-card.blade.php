<li class="saci-card" data-saci-card-key="{{ $template['path'] }}">
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
            if (expanded) {
                container.classList.remove('is-open');
                // allow transition to animate before display change
                setTimeout(() => { contentEl.style.display = 'none'; }, 240);
            } else {
                contentEl.style.display = 'block';
                // next tick to trigger CSS transition
                requestAnimationFrame(() => container.classList.add('is-open'));
            }
            header.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            try {
                const key = container.getAttribute('data-saci-card-key');
                if (key) localStorage.setItem('saci.card.' + key, expanded ? '0' : '1');
            } catch(e) {}
        "
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

