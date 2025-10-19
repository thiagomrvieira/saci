<div>


    @php
        $req = $resources['request'] ?? [];
        $res = $resources['response'] ?? [];
        $auth = $resources['auth'] ?? [];
        $metaTable = view('saci::partials._request-meta', ['req' => $req, 'res' => $res, 'requestId' => ($requestId ?? null)])->render();
        $metaContent = $metaTable
            . '<div class="saci-meta" style="margin-top:8px;">'
            . '<span class="saci-badge">Auth: ' . ((($auth['authenticated'] ?? false) ? 'yes' : 'no')) . '</span>'
            . (!empty($auth['id']) ? (' <span class="saci-badge">id: ' . $auth['id'] . '</span>') : '')
            . (!empty($auth['email']) ? (' <span class="saci-badge">email: ' . $auth['email'] . '</span>') : '')
            . '</div>';
    @endphp

    @include('saci::partials.card', [
        'key' => 'request-meta',
        'title' => '',
        'open' => true,
        'content' => $metaContent,
    ])




</div>


