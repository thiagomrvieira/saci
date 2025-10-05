<script>
    (function() {
        // Restore per-card expanded state
        document.querySelectorAll('#saci-content .saci-card').forEach(card => {
            const key = card.getAttribute('data-saci-card-key');
            if (!key) return;
            let open = null;
            try { open = localStorage.getItem('saci.card.' + key); } catch(e) {}
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
                let open = null;
                try { open = localStorage.getItem('saci.var.' + cardKey + '.' + varKey); } catch(e) {}
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
    })();
</script>

