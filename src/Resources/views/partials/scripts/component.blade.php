<script>
    window.saciBar = function() {
        return {
            collapsed: false,
            tab: 'views',
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

