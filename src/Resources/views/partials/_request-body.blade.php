@php
    $rawPreview = $req['raw_preview'] ?? '';
    $rawDumpId = $req['raw_dump_id'] ?? null;
    $requestId = $requestId ?? null;
@endphp

<table class="saci-table" style="width:100%;">
    <thead>
    <tr>
        <th class="saci-col-name">Body</th>
        <th>Preview</th>
    </tr>
    </thead>
    <tbody>
    <tr data-saci-var-key="request.body" @click.stop="onVarRowClick($el)" tabindex="0" @keydown.enter.prevent="$el.click()" @keydown.space.prevent="$el.click()">
        <td class="saci-var-name">raw</td>
        <td class="saci-preview">{{ $rawPreview !== '' ? $rawPreview : '[empty]' }}</td>
    </tr>
    <tr class="saci-value-row" style="display:none;">
        <td colspan="2" style="padding: 6px 4px; position: relative;">
            <div class="saci-dump" data-dump-id="{{ $rawDumpId }}" data-request-id="{{ $requestId }}">
                <div class="saci-dump-loading" style="display:none;">Loading…</div>
                <div class="saci-dump-content"></div>
            </div>
        </td>
    </tr>
    </tbody>
</table>
