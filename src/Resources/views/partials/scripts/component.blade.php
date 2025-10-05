<script>
    // Lightweight Alpine component factory for the Saci bar
    window.saciBar = function() {
        return {
            collapsed: false,
            init() {
                try { this.collapsed = localStorage.getItem('saci.collapsed') === '1'; } catch (e) {}
                if (!this.collapsed && window.Alpine) {
                    const store = Alpine.store('saci');
                    if (store && typeof store.reapplyHeight === 'function') store.reapplyHeight();
                }
            },
            toggle() {
                this.collapsed = !this.collapsed;
                try { localStorage.setItem('saci.collapsed', this.collapsed ? '1' : '0'); } catch (e) {}
                if (!this.collapsed && window.Alpine) {
                    const store = Alpine.store('saci');
                    if (store && typeof store.reapplyHeight === 'function') store.reapplyHeight();
                }
            },
            expandAll() { window.dispatchEvent(new CustomEvent('saci-expand-all')); },
            collapseAll() { window.dispatchEvent(new CustomEvent('saci-collapse-all')); }
        };
    };
</script>

