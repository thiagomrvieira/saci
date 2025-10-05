<script>
    (function() {
        const saciEl = document.getElementById('saci');
        const headerEl = document.getElementById('saci-header');
        const contentEl = document.getElementById('saci-content');
        if (!saciEl || !headerEl || !contentEl) return;

        // Globals used by header click guard
        window.saciIsResizing = false;
        window.saciDidResize = false;

        let startY = 0;
        let startHeight = 0;
        let pending = false;
        const MIN = 120; // px
        const MAX = window.innerHeight * 0.9; // 90vh cap

        const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
        const apply = (h) => {
            const px = clamp(h, MIN, MAX);
            saciEl.style.maxHeight = px + 'px';
            contentEl.style.maxHeight = `calc(${px}px - 36px)`; // subtract header height
            try { localStorage.setItem('saci.maxHeightPx', String(px)); } catch (e) {}
        };

        // restore saved height
        try {
            const saved = parseInt(localStorage.getItem('saci.maxHeightPx') || '', 10);
            if (!isNaN(saved)) apply(saved);
        } catch (e) {}

        // reapply on expand
        const obs = new MutationObserver(() => {
            if (!saciEl.classList.contains('saci-collapsed')) {
                try {
                    const saved = parseInt(localStorage.getItem('saci.maxHeightPx') || '', 10);
                    if (!isNaN(saved)) apply(saved);
                } catch (e) {}
            }
        });
        obs.observe(saciEl, { attributes: true, attributeFilter: ['class'] });

        const onMove = (e) => {
            if (!window.saciIsResizing) return;
            const delta = startY - e.clientY; // up increases height
            apply(startHeight + delta);
            e.preventDefault();
        };
        const onUp = () => {
            if (!window.saciIsResizing) return;
            window.saciIsResizing = false;
            window.saciDidResize = true;
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        };

        headerEl.addEventListener('mousedown', (e) => {
            if (saciEl.classList.contains('saci-collapsed')) return;
            pending = true;
            startY = e.clientY;
            startHeight = saciEl.getBoundingClientRect().height;
            const threshold = 3;

            const move = (ev) => {
                if (!pending) return;
                if (Math.abs(ev.clientY - startY) >= threshold) {
                    pending = false;
                    window.saciIsResizing = true;
                    document.body.style.userSelect = 'none';
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                    onMove(ev);
                }
            };
            const up = () => {
                if (pending) pending = false; // treat as click
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
            };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        });
    })();
</script>

