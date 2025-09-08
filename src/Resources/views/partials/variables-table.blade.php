<table
    class="saci-table"
>
    <thead>
        <tr>
            <th class="saci-col-name">Variable</th>
            <th class="saci-col-type">Type</th>
            <th>Preview</th>
            <th class="saci-actions">Actions</th>
        </tr>
    </thead>
    <tbody>
    @foreach($data as $key => $info)
        <tr>
            <td class="saci-var-name">{{ $key }}</td>
            <td class="saci-var-type">{{ $info['type'] ?? gettype($info) }}</td>
            <td class="saci-preview">
                {{ is_array($info) && isset($info['preview']) ? $info['preview'] : '' }}
            </td>
            <td class="saci-actions">
                <button
                    class="saci-toggle-btn"
                    @click.prevent.stop="
                        const row = $el.closest('tr');
                        const valueRow = row.nextElementSibling;
                        if (!valueRow) return;
                        const hidden = valueRow.style.display === 'none';
                        valueRow.style.display = hidden ? 'table-row' : 'none';
                        $el.textContent = hidden ? 'Hide' : 'Show';
                    "
                >Show</button>
            </td>
        </tr>
        <tr class="saci-value-row" style="display:none;">
            <td colspan="3" style="padding: 6px 4px;">
<pre class="saci-pre">
{{ json_encode(is_array($info) ? ($info['value'] ?? null) : $info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
</pre>
            </td>
            <td class="saci-actions"></td>
        </tr>
    @endforeach
    </tbody>
</table>

