<script>
    // Lightweight Alpine component factory for the Saci bar
    window.saciBar = function() {
        return {
            collapsed: false,
            tab: 'views',
            init() {
                try { this.collapsed = localStorage.getItem('saci.collapsed') === '1'; } catch (e) {}
                try { const saved = localStorage.getItem('saci.tab'); if (saved) this.tab = saved; } catch (e) {}
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
            saveTab() { try { localStorage.setItem('saci.tab', this.tab); } catch (e) {} },
            expandAll() { window.dispatchEvent(new CustomEvent('saci-expand-all')); },
            collapseAll() { window.dispatchEvent(new CustomEvent('saci-collapse-all')); }
        };
    };
</script>

