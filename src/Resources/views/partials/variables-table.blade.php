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
        <tr data-saci-var-key="{{ $key }}">
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
                        try {
                            const card = $el.closest('.saci-card');
                            const cardKey = card ? card.getAttribute('data-saci-card-key') : '';
                            const varKey = row.getAttribute('data-saci-var-key') || '';
                            if (cardKey && varKey) localStorage.setItem('saci.var.' + cardKey + '.' + varKey, hidden ? '1' : '0');
                        } catch(e) {}
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

