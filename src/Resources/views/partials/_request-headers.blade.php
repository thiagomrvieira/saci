@php
    $headersPreview = $req['headers_preview'] ?? '';
    $headersDumpId = $req['headers_dump_id'] ?? null;
    $requestId = $requestId ?? null;
@endphp

<table class="saci-table" style="width:100%;">
    <thead>
    <tr>
        <th class="saci-col-name">Headers</th>
        <th>Preview</th>
    </tr>
    </thead>
    <tbody>
    <tr data-saci-var-key="request.headers" tabindex="0">
        <td class="saci-var-name">headers</td>
        <td class="saci-preview">{{ $headersPreview }}</td>
    </tr>
    <tr class="saci-value-row" style="display:none;">
        <td colspan="2" style="padding: 6px 4px; position: relative;">
            <div class="saci-dump" data-dump-id="{{ $headersDumpId }}" data-request-id="{{ $requestId }}">
                <div class="saci-dump-loading" style="display:none;">Loading…</div>
                <div class="saci-dump-content"></div>
            </div>
        </td>
    </tr>
    </tbody>
</table>
