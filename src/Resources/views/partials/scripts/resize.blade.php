<script>
    document.addEventListener('alpine:initialized', () => {
        const saci = Alpine.store('saci');
        const saciEl = document.getElementById('saci');
        const headerEl = document.getElementById('saci-header');
        if (!saci || !saciEl || !headerEl) return;

        let startY = 0;
        let startHeight = 0;
        let pending = false;
        const MIN = 120;
        const MAX = window.innerHeight * 0.9;
        const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

        // reapply saved height on init
        saci.reapplyHeight();

        const onMove = (e) => {
            if (!saci.isResizing) return;
            const delta = startY - e.clientY;
            saci.applyHeight(clamp(startHeight + delta, MIN, MAX));
            e.preventDefault();
        };
        const onUp = () => {
            if (!saci.isResizing) return;
            saci.isResizing = false;
            saci.didResize = true;
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
                    saci.isResizing = true;
                    document.body.style.userSelect = 'none';
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                    onMove(ev);
                }
            };
            const up = () => {
                if (pending) pending = false;
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
            };
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        });
    });
</script>

