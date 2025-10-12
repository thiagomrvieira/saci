<table class="saci-table">
    <tbody>
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
