<div>
    <div class="saci-card" data-saci-card-key="request-route" style="margin-bottom: 12px;">
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
                    setTimeout(() => { contentEl.style.display = 'none'; }, 240);
                } else {
                    contentEl.style.display = 'block';
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
            <span class="saci-path">Route</span>
        </div>
        <div class="saci-card-content" style="display: none;">
            <table class="saci-table">
                <tbody>
                @php $route = $resources['route'] ?? []; @endphp
                @foreach([
                    'name' => $route['name'] ?? null,
                    'uri' => $route['uri'] ?? null,
                    'methods' => isset($route['methods']) ? implode(', ', (array)$route['methods']) : null,
                    'action' => $route['action'] ?? null,
                    'controller' => $route['controller'] ?? null,
                    'controller_method' => $route['controller_method'] ?? null,
                    'controller_file' => $route['controller_file'] ?? null,
                ] as $label => $val)
                    @if(!is_null($val) && $val !== '')
                        <tr>
                            <td class="saci-col-name">{{ $label }}</td>
                            <td colspan="3" class="saci-preview">{{ is_string($val) ? $val : json_encode($val) }}</td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="saci-card" data-saci-card-key="request-meta">
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
                    setTimeout(() => { contentEl.style.display = 'none'; }, 240);
                } else {
                    contentEl.style.display = 'block';
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
            <span class="saci-path">Request / Response</span>
        </div>
        <div class="saci-card-content" style="display: none;">
            @php
                $req = $resources['request'] ?? [];
                $res = $resources['response'] ?? [];
                $auth = $resources['auth'] ?? [];
            @endphp
            <table class="saci-table">
                <tbody>
                    @foreach([
                        'status' => $res['status'] ?? null,
                        'duration' => isset($res['duration_ms']) ? (($res['duration_ms']).'ms') : null,
                        'full_url' => $req['full_url'] ?? null,
                        'request_format' => $req['format'] ?? null,
                        'response' => $res['content_type'] ?? null,
                        'method' => $req['method'] ?? null,
                    ] as $label => $val)
                        @if(!is_null($val) && $val !== '')
                            <tr>
                                <td class="saci-col-name">{{ $label }}</td>
                                <td colspan="3" class="saci-preview">{{ is_string($val) ? $val : json_encode($val) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
            <div class="saci-card" style="margin-top:10px;">
                <div class="saci-card-toggle" role="button" tabindex="0" aria-expanded="false"
                     @click.stop="
                        const header = $el;
                        const container = header.parentElement;
                        const contentEl = container.querySelector('.saci-card-content');
                        if (!contentEl) return;
                        const expanded = header.getAttribute('aria-expanded') === 'true';
                        if (expanded) {
                            container.classList.remove('is-open');
                            setTimeout(() => { contentEl.style.display = 'none'; }, 240);
                        } else {
                            contentEl.style.display = 'block';
                            requestAnimationFrame(() => container.classList.add('is-open'));
                        }
                        header.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                     "
                     @keydown.enter.prevent="$el.click()"
                     @keydown.space.prevent="$el.click()"
                >
                    <span class="saci-path">Request headers</span>
                </div>
                <div class="saci-card-content" style="display:none;">
<pre class="saci-pre">{{ json_encode($req['headers_all'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
                </div>
            </div>
            <div class="saci-card" style="margin-top:10px;">
                <div class="saci-card-toggle" role="button" tabindex="0" aria-expanded="false"
                     @click.stop="
                        const header = $el;
                        const container = header.parentElement;
                        const contentEl = container.querySelector('.saci-card-content');
                        if (!contentEl) return;
                        const expanded = header.getAttribute('aria-expanded') === 'true';
                        if (expanded) {
                            container.classList.remove('is-open');
                            setTimeout(() => { contentEl.style.display = 'none'; }, 240);
                        } else {
                            contentEl.style.display = 'block';
                            requestAnimationFrame(() => container.classList.add('is-open'));
                        }
                        header.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                     "
                     @keydown.enter.prevent="$el.click()"
                     @keydown.space.prevent="$el.click()"
                >
                    <span class="saci-path">Request body</span>
                </div>
                <div class="saci-card-content" style="display:none;">
<pre class="saci-pre">{{ (string) ($req['raw'] ?? '') }}</pre>
                </div>
            </div>
            <div class="saci-meta" style="margin-top:8px;">
                <span class="saci-badge">Auth: {{ ($auth['authenticated'] ?? false) ? 'yes' : 'no' }}</span>
                @if(!empty($auth['id']))<span class="saci-badge">id: {{ $auth['id'] }}</span>@endif
                @if(!empty($auth['email']))<span class="saci-badge">email: {{ $auth['email'] }}</span>@endif
            </div>
        </div>
    </div>


</div>


