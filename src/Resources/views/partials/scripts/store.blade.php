<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('saci', {
            isResizing: false,
            didResize: false,
            get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
            set(key, value) { try { localStorage.setItem(key, value); } catch (e) {} },
            applyHeight(px) {
                const saciEl = document.getElementById('saci');
                const contentEl = document.getElementById('saci-content');
                if (!saciEl || !contentEl) return;
                saciEl.style.maxHeight = px + 'px';
                contentEl.style.maxHeight = 'calc(' + px + 'px - 36px)';
                this.set('saci.maxHeightPx', String(px));
            },
            reapplyHeight() {
                const saved = parseInt(this.get('saci.maxHeightPx') || '', 10);
                if (!isNaN(saved)) this.applyHeight(saved);
            },
        });
    });
</script>
