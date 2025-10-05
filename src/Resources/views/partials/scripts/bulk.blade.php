<script>
    (function() {
        const expandAll = () => {
            document.querySelectorAll('#saci-content .saci-value-row').forEach(r => r.style.display = 'table-row');
            document.querySelectorAll('#saci-content .saci-toggle-btn').forEach(b => b.textContent = 'Hide');
            document.querySelectorAll('#saci-content .saci-card').forEach(card => {
                const content = card.querySelector('.saci-card-content');
                const toggle = card.querySelector('.saci-card-toggle');
                if (!content || !toggle) return;
                content.style.display = 'block';
                requestAnimationFrame(() => card.classList.add('is-open'));
                toggle.setAttribute('aria-expanded', 'true');
            });
        };

        const collapseAll = () => {
            document.querySelectorAll('#saci-content .saci-value-row').forEach(r => r.style.display = 'none');
            document.querySelectorAll('#saci-content .saci-toggle-btn').forEach(b => b.textContent = 'Show');
            document.querySelectorAll('#saci-content .saci-card').forEach(card => {
                const content = card.querySelector('.saci-card-content');
                const toggle = card.querySelector('.saci-card-toggle');
                if (!content || !toggle) return;
                card.classList.remove('is-open');
                setTimeout(() => { content.style.display = 'none'; }, 240);
                toggle.setAttribute('aria-expanded', 'false');
            });
        };

        window.addEventListener('saci-expand-all', expandAll);
        window.addEventListener('saci-collapse-all', collapseAll);
    })();
</script>

