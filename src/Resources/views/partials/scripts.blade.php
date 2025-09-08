<script>
    (function() {
        const expandAll = () => {
            document.querySelectorAll('#saci-content .saci-value-row').forEach(r => r.style.display = 'table-row');
            document.querySelectorAll('#saci-content .saci-toggle-btn').forEach(b => b.textContent = 'Hide');
            document.querySelectorAll('#saci-content .saci-card-content').forEach(c => c.style.display = 'block');
            document.querySelectorAll('#saci-content .saci-card-toggle').forEach(t => t.setAttribute('aria-expanded', 'true'));
        };

        const collapseAll = () => {
            document.querySelectorAll('#saci-content .saci-value-row').forEach(r => r.style.display = 'none');
            document.querySelectorAll('#saci-content .saci-toggle-btn').forEach(b => b.textContent = 'Show');
            document.querySelectorAll('#saci-content .saci-card-content').forEach(c => c.style.display = 'none');
            document.querySelectorAll('#saci-content .saci-card-toggle').forEach(t => t.setAttribute('aria-expanded', 'false'));
        };

        window.addEventListener('saci-expand-all', expandAll);
        window.addEventListener('saci-collapse-all', collapseAll);
    })();
</script>

