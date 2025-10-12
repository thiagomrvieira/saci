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
            @click.stop="toggleVarRow($el)"
            tabindex="0"
            @keydown.enter.prevent="$el.click()"
            @keydown.space.prevent="$el.click()"
        >
            <td class="saci-var-name">{{ $key }}</td>
            <td class="saci-var-type">{{ $info['type'] ?? gettype($info) }}</td>
            <td class="saci-preview">
                {{ is_array($info) && isset($info['preview']) ? $info['preview'] : '' }}
            </td>

        </tr>
        <tr class="saci-value-row" style="display:none;">
            <td colspan="3" style="padding: 6px 4px; position: relative;">
<pre class="saci-pre">{{
    (json_encode(
        is_array($info) ? ($info['value'] ?? null) : $info,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
    )) ?: 'null'
}}</pre>
            </td>

        </tr>
    @endforeach
    </tbody>
</table>

