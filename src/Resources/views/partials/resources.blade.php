<div>
    <div class="saci-card" style="margin-bottom: 12px;">
        <div class="saci-card-toggle" aria-expanded="true">
            <span class="saci-path">Route</span>
        </div>
        <div class="saci-card-content" style="display: block;">
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

    <div class="saci-card">
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
            <span class="saci-path">Resolved services (App::resolving)</span>
        </div>
        <div class="saci-card-content" style="display: none;">
            @php $services = $resources['services'] ?? []; @endphp
            @if(!empty($services))
                <ul style="margin: 0; padding: 0; list-style: none;">
                    @foreach($services as $svc)
                        <li class="saci-pre" style="margin-bottom: 6px;">{{ $svc }}</li>
                    @endforeach
                </ul>
            @else
                <div class="saci-pre">No services resolved for this request.</div>
            @endif
        </div>
    </div>
</div>


