<script>
    window.saciBar = function() {
        return {
            collapsed: false,
            tab: 'views',
            // Tooltip popover state
            tooltipOpen: false,
            tooltipText: '',
            tooltipX: 0,
            tooltipY: 0,
            tooltipPlacement: 'top', // 'top' | 'bottom'
            showTooltip(evt, text) {
                try {
                    const target = evt && evt.currentTarget ? evt.currentTarget : null;
                    const fromData = target && target.getAttribute ? (target.getAttribute('data-saci-tooltip') || '') : '';
                    this.tooltipText = (typeof text === 'string' && text.length) ? text : fromData;
                    if (!target) { this.tooltipOpen = true; return; }
                    const rect = target.getBoundingClientRect();
                    const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    // Preferred width and height estimates; refined after render
                    const preferredWidth = Math.min(420, Math.max(220, rect.width * 2.5));
                    const padding = 8; // viewport padding
                    // Decide placement based on available space
                    const spaceAbove = rect.top;
                    const spaceBelow = viewportHeight - rect.bottom;
                    this.tooltipPlacement = spaceAbove > spaceBelow ? 'top' : 'bottom';
                    // Initial x centered to target
                    let x = rect.left + rect.width / 2 - preferredWidth / 2;
                    x = Math.max(padding, Math.min(x, viewportWidth - preferredWidth - padding));
                    // y based on placement
                    let y = this.tooltipPlacement === 'top' ? rect.top - 10 : rect.bottom + 10;
                    // Convert to page coordinates for fixed positioning
                    this.tooltipX = Math.round(x);
                    this.tooltipY = Math.round(y);
                    this.tooltipOpen = true;
                    // After render, try to refine position using actual size
                    requestAnimationFrame(() => {
                        const el = document.getElementById('saci-popover');
                        if (!el) return;
                        const popRect = el.getBoundingClientRect();
                        let adjX = rect.left + rect.width / 2 - popRect.width / 2;
                        adjX = Math.max(padding, Math.min(adjX, viewportWidth - popRect.width - padding));
                        let adjY = this.tooltipPlacement === 'top' ? rect.top - popRect.height - 10 : rect.bottom + 10;
                        this.tooltipX = Math.round(adjX);
                        this.tooltipY = Math.round(adjY);
                    });
                } catch(e) { this.tooltipOpen = true; }
            },
            hideTooltip() {
                this.tooltipOpen = false;
                this.tooltipText = '';
            },
            toggleCard(container) {
                const header = container.querySelector('.saci-card-toggle');
                const contentEl = container.querySelector('.saci-card-content');
                if (!contentEl || !header) return;
                const expanded = header.getAttribute('aria-expanded') === 'true';
                if (expanded) {
                    container.classList.remove('is-open');
                    setTimeout(() => { contentEl.style.display = 'none'; }, 240);
                } else {
                    contentEl.style.display = 'block';
                    requestAnimationFrame(() => container.classList.add('is-open'));
                }
                header.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                try {
                    const key = container.getAttribute('data-saci-card-key');
                    if (key) localStorage.setItem('saci.card.' + key, expanded ? '0' : '1');
                } catch(e) {}
            },
            toggleVarRow(row) {
                const valueRow = row.nextElementSibling;
                if (!valueRow) return;
                const isHidden = valueRow.style.display === 'none';
                valueRow.style.display = isHidden ? 'table-row' : 'none';
                const btn = row.querySelector('.saci-toggle-btn');
                if (btn) btn.textContent = isHidden ? 'Hide' : 'Show';
                try {
                    const card = row.closest('.saci-card');
                    const cardKey = card ? card.getAttribute('data-saci-card-key') : '';
                    const varKey = row.getAttribute('data-saci-var-key') || '';
                    if (cardKey && varKey) localStorage.setItem('saci.var.' + cardKey + '.' + varKey, isHidden ? '1' : '0');
                } catch(e) {}
            },
            init() {
                try { this.collapsed = localStorage.getItem('saci.collapsed') === '1'; } catch (e) {}
                // Start collapsed unless user previously expanded
                if (localStorage.getItem('saci.collapsed') === null) {
                    this.collapsed = true;
                }
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
            expandAll() {
                const content = document.getElementById('saci-content');
                if (!content) return;
                content.querySelectorAll('.saci-value-row').forEach(r => r.style.display = 'table-row');
                content.querySelectorAll('.saci-card').forEach(card => {
                    const c = card.querySelector('.saci-card-content');
                    const t = card.querySelector('.saci-card-toggle');
                    if (!c || !t) return;
                    c.style.display = 'block';
                    requestAnimationFrame(() => card.classList.add('is-open'));
                    t.setAttribute('aria-expanded', 'true');
                });
            },
            collapseAll() {
                const content = document.getElementById('saci-content');
                if (!content) return;
                content.querySelectorAll('.saci-value-row').forEach(r => r.style.display = 'none');
                content.querySelectorAll('.saci-card').forEach(card => {
                    const c = card.querySelector('.saci-card-content');
                    const t = card.querySelector('.saci-card-toggle');
                    if (!c || !t) return;
                    card.classList.remove('is-open');
                    setTimeout(() => { c.style.display = 'none'; }, 240);
                    t.setAttribute('aria-expanded', 'false');
                });
            }
        };
    };
</script>

