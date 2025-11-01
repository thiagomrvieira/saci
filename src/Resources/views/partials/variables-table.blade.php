<table
    class="saci-table"
>
    <thead>
        <tr>
            <th class="saci-col-name">Variable</th>
            <th class="saci-col-type">Type</th>
            <th>Preview</th>
        </tr>
    </thead>
    <tbody>
    @foreach($data as $key => $info)
        <tr
            data-saci-var-key="{{ $key }}"
            tabindex="0"
        >
            <td class="saci-var-name">{{ $key }}</td>
            <td class="saci-var-type">{{ $info['type'] ?? gettype($info) }}</td>
            <td class="saci-preview">
                {{ is_array($info) && isset($info['preview']) ? $info['preview'] : '' }}
            </td>

        </tr>
        <tr class="saci-value-row" style="display:none;">
            <td colspan="3" style="padding: 6px 4px; position: relative;">
                <div
                    class="saci-dump"
                    data-dump-id="{{ is_array($info) ? ($info['dump_id'] ?? '') : '' }}"
                    data-request-id="{{ $requestId ?? '' }}"
                >
                    <div class="saci-dump-loading" style="display:none;">Loading…</div>
                    <div class="saci-dump-content"></div>
                </div>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

