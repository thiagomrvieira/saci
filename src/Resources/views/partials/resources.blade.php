<div>
    @php
        $route = $resources['route'] ?? [];
        $routeTable = view('saci::partials._request-route', compact('route'))->render();
    @endphp
    @include('saci::partials.card', [
        'key' => 'request-route',
        'title' => 'Route',
        'open' => false,
        'content' => $routeTable,
    ])

    @php
        $req = $resources['request'] ?? [];
        $res = $resources['response'] ?? [];
        $auth = $resources['auth'] ?? [];
        $metaTable = view('saci::partials._request-meta', compact('req','res'))->render();
        $headers = view('saci::partials._request-headers', compact('req'))->render();
        $body = view('saci::partials._request-body', compact('req'))->render();
        $metaContent = $metaTable . view('saci::partials.card', [
            'key' => 'request-headers',
            'title' => 'Request headers',
            'open' => false,
            'content' => $headers,
        ])->render() . view('saci::partials.card', [
            'key' => 'request-body',
            'title' => 'Request body',
            'open' => false,
            'content' => $body,
        ])->render() . '<div class="saci-meta" style="margin-top:8px;">'
            .'<span class="saci-badge">Auth: '.((($auth['authenticated'] ?? false) ? 'yes' : 'no')).'</span>'
            .(!empty($auth['id']) ? (' <span class="saci-badge">id: '.$auth['id'].'</span>') : '')
            .(!empty($auth['email']) ? (' <span class="saci-badge">email: '.$auth['email'].'</span>') : '')
            .'</div>';
    @endphp
    @include('saci::partials.card', [
        'key' => 'request-meta',
        'title' => 'Request / Response',
        'open' => false,
        'content' => $metaContent,
    ])


</div>


