<table class="saci-table">
    <tbody>
        @php
            $durationDisplay = isset($res['duration_ms']) && is_numeric($res['duration_ms'])
                ? \ThiagoVieira\Saci\Support\PerformanceFormatter::formatMs((float) $res['duration_ms'])
                : null;
        @endphp
        @foreach([
            'status' => $res['status'] ?? null,
            'duration' => $durationDisplay,
            'full_url' => $req['full_url'] ?? null,
            'request_format' => $req['format'] ?? null,
            'response' => $res['content_type'] ?? null,
            'method' => $req['method'] ?? null,
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

        @php $requestId = $requestId ?? null; @endphp

        @foreach([
            'headers' => ['preview' => $req['headers_preview'] ?? '', 'dump_id' => $req['headers_dump_id'] ?? null],
            'body' => ['preview' => $req['raw_preview'] ?? ($req['raw'] ?? ''), 'dump_id' => $req['raw_dump_id'] ?? null],
            'query' => ['preview' => $req['query_preview'] ?? '', 'dump_id' => $req['query_dump_id'] ?? null],
            'cookies' => ['preview' => $req['cookies_preview'] ?? '', 'dump_id' => $req['cookies_dump_id'] ?? null],
            'session' => ['preview' => $req['session_preview'] ?? '', 'dump_id' => $req['session_dump_id'] ?? null],
        ] as $label => $info)
            <tr
                data-saci-var-key="request.{{ $label }}"
                @click.stop="onVarRowClick($el)"
                tabindex="0"
                @keydown.enter.prevent="$el.click()"
                @keydown.space.prevent="$el.click()"
            >
                <td class="saci-col-name">{{ $label }}</td>
                <td colspan="3" class="saci-preview">
                    <span class="saci-inline-preview">{{ $info['preview'] !== '' ? $info['preview'] : '[empty]' }}</span>
                    <div
                        class="saci-dump-inline"
                        data-dump-id="{{ $info['dump_id'] }}"
                        data-request-id="{{ $requestId }}"
                        style="display:none;"
                    >
                        <div class="saci-dump-loading" style="display:none;">
                            Loading…
                        </div>
                        <div class="saci-dump-content"></div>
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
