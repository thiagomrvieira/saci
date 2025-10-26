@php
    $requestId = $requestId ?? null;
@endphp

<table class="saci-table">
    <tbody>
        @foreach([
            'name' => $route['name'] ?? null,
            'uri' => $route['uri'] ?? null,
            'methods' => isset($route['methods']) ? implode(', ', (array)$route['methods']) : null,
            'domain' => $route['domain'] ?? null,
            'prefix' => $route['prefix'] ?? null,
            'action' => $route['action'] ?? null,
            'controller' => $route['controller'] ?? null,
            'controller_method' => $route['controller_method'] ?? null,
            'controller_file' => $route['controller_file'] ?? null,
        ] as $label => $val)
            @if(!is_null($val) && $val !== '')
                <tr>
                    <td class="saci-col-name">{{ $label }}</td>
                    <td colspan="3" class="saci-preview">
                        {{ is_string($val) ? $val : json_encode($val) }}
                    </td>
                </tr>
            @endif
        @endforeach

        @foreach([
            'parameters' => ['preview' => $route['parameters_preview'] ?? '[empty]', 'dump_id' => $route['parameters_dump_id'] ?? null, 'inline_html' => $route['parameters_inline_html'] ?? null],
            'middleware' => ['preview' => $route['middleware_preview'] ?? '[empty]', 'dump_id' => $route['middleware_dump_id'] ?? null, 'inline_html' => $route['middleware_inline_html'] ?? null],
            'where' => ['preview' => $route['where_preview'] ?? '[empty]', 'dump_id' => $route['where_dump_id'] ?? null, 'inline_html' => $route['where_inline_html'] ?? null],
            'compiled' => ['preview' => $route['compiled_preview'] ?? '[empty]', 'dump_id' => $route['compiled_dump_id'] ?? null, 'inline_html' => $route['compiled_inline_html'] ?? null],
        ] as $label => $info)
            <tr
                data-saci-var-key="route.{{ $label }}"
                @click.stop="onVarRowClick($el)"
                tabindex="0"
                @keydown.enter.prevent="$el.click()"
                @keydown.space.prevent="$el.click()"
            >
                <td class="saci-col-name">{{ $label }}</td>
                <td colspan="3" class="saci-preview">
                    <span class="saci-inline-preview">{{ $info['preview'] }}</span>
                    <div
                        class="saci-dump-inline"
                        data-dump-id="{{ $info['dump_id'] }}"
                        data-request-id="{{ $requestId }}"
                        style="display:none;"
                    >
                        <div class="saci-dump-loading" style="display:none;">
                            Loading…
                        </div>
                        <div class="saci-dump-content">{!! $info['inline_html'] ?? '' !!}</div>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
