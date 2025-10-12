<script>
    document.addEventListener('alpine:initialized', () => {
        const store = Alpine.store('saci');
        if (!store) return;

        // Restore per-card expanded state (including Request tab cards)
        document.querySelectorAll('#saci-content .saci-card').forEach(card => {
            const key = card.getAttribute('data-saci-card-key');
            if (!key) return;
            const open = store.get('saci.card.' + key);
            if (open === '1') {
                const content = card.querySelector('.saci-card-content');
                const toggle = card.querySelector('.saci-card-toggle');
                if (content && toggle) {
                    content.style.display = 'block';
                    card.classList.add('is-open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            }
        });

        // Restore per-variable expanded state
        document.querySelectorAll('#saci-content .saci-card').forEach(card => {
            const cardKey = card.getAttribute('data-saci-card-key');
            if (!cardKey) return;
            card.querySelectorAll('tr[data-saci-var-key]').forEach(row => {
                const varKey = row.getAttribute('data-saci-var-key');
                if (!varKey) return;
                const open = store.get('saci.var.' + cardKey + '.' + varKey);
                if (open === '1') {
                    const valueRow = row.nextElementSibling;
                    const btn = row.querySelector('.saci-toggle-btn');
                    if (valueRow && btn) {
                        valueRow.style.display = 'table-row';
                        btn.textContent = 'Hide';
                    }
                }
            });
        });
    });
</script>

