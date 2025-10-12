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
