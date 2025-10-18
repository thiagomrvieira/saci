 /* Saci Debug Bar JS (requires Alpine.js) */
 (function(){
    /**
     * LocalStorage helpers with safe fallbacks.
     * @type {{get:(key:string)=>string|null, set:(key:string,value:string)=>void}}
     */
    const storage = {
        /** @param {string} key @returns {string|null} */
        get(key) {
            try {
                return localStorage.getItem(key);
            } catch (e) {
                return null;
            }
        },
        /** @param {string} key @param {string} value @returns {void} */
        set(key, value) {
            try {
                localStorage.setItem(key, value);
            } catch (e) {}
        }
    };

    /**
     * AJAX helpers for lazy dumps.
     * @type {{fetchHtml:(requestId:string,dumpId:string)=>Promise<string>}}
     */
    const dumps = {
        /** Fetch dump HTML for a given request/dump id pair. */
        async fetchHtml(requestId, dumpId) {
            const url = `/__saci/dump/${encodeURIComponent(requestId)}/${encodeURIComponent(dumpId)}`;
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            });
            if (!response.ok) {
                throw new Error('Failed to load dump');
            }
            return await response.text();
        }
    };

    /** Clamp a number between bounds. @param {number} v @param {number} lo @param {number} hi @returns {number} */
    const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

    /** Alpine component factory for the Saci bar. @returns {object} */
    window.saciBar = function() {
        return {
            collapsed: true,
            tab: 'views',
            isResizing: false,
            didResize: false,
            tooltipOpen: false,
            tooltipText: '',
            tooltipX: 0,
            tooltipY: 0,
            tooltipPlacement: 'top',

            /** Component setup: restore state, height, and resize handlers. */
            init() {
                try {
                    this.collapsed = storage.get('saci.collapsed') === '1';
                } catch (e) {}
                if (storage.get('saci.collapsed') === null) {
                    this.collapsed = true;
                }
                try {
                    const saved = storage.get('saci.tab');
                    if (saved) this.tab = saved;
                } catch (e) {}
                this.reapplyHeight();
                this.restoreCards();
                this.restoreVarRows();
                this.setupResize();
            },

            /** Reapplies persisted height to the bar. */
            reapplyHeight() {
                const saciEl = this.$root;
                const contentEl = this.$root.querySelector('#saci-content');
                const saved = parseInt(storage.get('saci.maxHeightPx') || '', 10);
                if (!isNaN(saved) && saciEl && contentEl) {
                    saciEl.style.maxHeight = saved + 'px';
                    contentEl.style.maxHeight = 'calc(' + saved + 'px - 36px)';
                }
            },

            /** Applies and persists a new height. @param {number} px */
            applyHeight(px) {
                const saciEl = this.$root;
                const contentEl = this.$root.querySelector('#saci-content');
                if (!saciEl || !contentEl) return;
                saciEl.style.maxHeight = px + 'px';
                contentEl.style.maxHeight = 'calc(' + px + 'px - 36px)';
                storage.set('saci.maxHeightPx', String(px));
            },

            /** Restores open/closed state of cards. */
            restoreCards() {
                this.$root.querySelectorAll('#saci-content .saci-card').forEach(card => {
                    const key = card.getAttribute('data-saci-card-key');
                    if (!key) return;
                    const open = storage.get('saci.card.' + key);
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
            },

            /** Restores open/closed state of variable rows. */
            restoreVarRows() {
                this.$root.querySelectorAll('#saci-content .saci-card').forEach(card => {
                    const cardKey = card.getAttribute('data-saci-card-key');
                    if (!cardKey) return;
                    card.querySelectorAll('tr[data-saci-var-key]').forEach(row => {
                        const varKey = row.getAttribute('data-saci-var-key');
                        if (!varKey) return;
                        const open = storage.get('saci.var.' + cardKey + '.' + varKey);
                        if (open === '1') {
                            const valueRow = row.nextElementSibling;
                            if (valueRow) valueRow.style.display = 'table-row';
                        }
                    });
                });
            },

            /** Enables drag-to-resize on the header. */
            setupResize() {
                const saciEl = this.$root;
                const headerEl = this.$root.querySelector('#saci-header');
                if (!saciEl || !headerEl) return;

                let startY = 0;
                let startHeight = 0;
                let pending = false;

                const MIN = 120;
                const MAX = window.innerHeight * 0.9;

                const onMove = (e) => {
                    if (!this.isResizing) return;
                    const delta = startY - e.clientY;
                    this.applyHeight(clamp(startHeight + delta, MIN, MAX));
                    e.preventDefault();
                };

                const onUp = () => {
                    if (!this.isResizing) return;
                    this.isResizing = false;
                    this.didResize = true;
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
                            this.isResizing = true;
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
            },

            /** Shows the tooltip near a target. @param {Event} evt @param {string=} text */
            showTooltip(evt, text) {
                try {
                    const target = evt && evt.currentTarget ? evt.currentTarget : null;
                    const fromData = target && target.getAttribute ? (target.getAttribute('data-saci-tooltip') || '') : '';
                    this.tooltipText = (typeof text === 'string' && text.length) ? text : fromData;
                    if (!target) { this.tooltipOpen = true; return; }
                    const rect = target.getBoundingClientRect();
                    const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
                    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    const preferredWidth = Math.min(420, Math.max(220, rect.width * 2.5));
                    const padding = 8;
                    const spaceAbove = rect.top; const spaceBelow = viewportHeight - rect.bottom;
                    this.tooltipPlacement = spaceAbove > spaceBelow ? 'top' : 'bottom';
                    let x = rect.left + rect.width / 2 - preferredWidth / 2;
                    x = Math.max(padding, Math.min(x, viewportWidth - preferredWidth - padding));
                    let y = this.tooltipPlacement === 'top' ? rect.top - 10 : rect.bottom + 10;
                    this.tooltipX = Math.round(x); this.tooltipY = Math.round(y); this.tooltipOpen = true;
                    requestAnimationFrame(() => {
                        const el = document.getElementById('saci-popover'); if (!el) return;
                        const popRect = el.getBoundingClientRect();
                        let adjX = rect.left + rect.width / 2 - popRect.width / 2;
                        adjX = Math.max(padding, Math.min(adjX, viewportWidth - popRect.width - padding));
                        let adjY = this.tooltipPlacement === 'top' ? rect.top - popRect.height - 10 : rect.bottom + 10;
                        this.tooltipX = Math.round(adjX); this.tooltipY = Math.round(adjY);
                    });
                } catch(e) { this.tooltipOpen = true; }
            },

            /** Hides the tooltip. */
            hideTooltip() {
                this.tooltipOpen = false;
                this.tooltipText = '';
            },

            /** Toggles a card open/closed. @param {HTMLElement} container */
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
                    if (key) {
                        storage.set('saci.card.' + key, expanded ? '0' : '1');
                    }
                } catch (e) {}
            },

            /** Toggles a variable row under a view. @param {HTMLTableRowElement} row */
            toggleVarRow(row) {
                const valueRow = row.nextElementSibling;
                if (!valueRow) return;
                const isHidden = valueRow.style.display === 'none';
                valueRow.style.display = isHidden ? 'table-row' : 'none';
                try {
                    const card = row.closest('.saci-card');
                    const cardKey = card ? card.getAttribute('data-saci-card-key') : '';
                    const varKey = row.getAttribute('data-saci-var-key') || '';
                    if (cardKey && varKey) {
                        storage.set('saci.var.' + cardKey + '.' + varKey, isHidden ? '1' : '0');
                    }
                } catch (e) {}
            },

            /** Handles click on a variable row with lazy dump loading. @param {HTMLTableRowElement} row */
            async onVarRowClick(row) {
                const valueRow = row.nextElementSibling; if (!valueRow) return;
                const container = valueRow.querySelector('.saci-dump');
                const hasDump = !!(container && container.getAttribute('data-dump-id'));
                if (hasDump) {
                    const dumpId = container.getAttribute('data-dump-id');
                    const requestId = container.getAttribute('data-request-id');
                    const content = container.querySelector('.saci-dump-content');
                    if (content && content.childElementCount === 0) {
                        await this.loadDumpFromIds(container, requestId, dumpId, row);
                        return;
                    }
                }
                this.toggleVarRow(row);
            },

            /** Loads a dump into the row from ids. @param {HTMLElement} container @param {string} requestId @param {string} dumpId @param {HTMLTableRowElement} row */
            async loadDumpFromIds(container, requestId, dumpId, row) {
                const loading = container.querySelector('.saci-dump-loading');
                const content = container.querySelector('.saci-dump-content');
                try { if (loading) loading.style.display = 'block';
                    const html = await dumps.fetchHtml(requestId, dumpId); if (content) content.innerHTML = html;
                } catch (e) { if (content) content.textContent = 'Failed to load dump'; }
                finally { if (loading) loading.style.display = 'none'; this.toggleVarRow(row); }
            },

            /** Collapses/expands the bar and persists state. */
            toggle() {
                this.collapsed = !this.collapsed;
                try { storage.set('saci.collapsed', this.collapsed ? '1' : '0'); } catch (e) {}
                if (!this.collapsed) this.reapplyHeight();
            },

            /** Persists current tab selection. */
            saveTab() { try { storage.set('saci.tab', this.tab); } catch (e) {} },

            /** Expands all cards and rows. */
            expandAll() {
                const content = document.getElementById('saci-content'); if (!content) return;
                content.querySelectorAll('.saci-value-row').forEach(r => r.style.display = 'table-row');
                content.querySelectorAll('.saci-card').forEach(card => {
                    const c = card.querySelector('.saci-card-content'); const t = card.querySelector('.saci-card-toggle'); if (!c || !t) return;
                    c.style.display = 'block'; requestAnimationFrame(() => card.classList.add('is-open')); t.setAttribute('aria-expanded', 'true');
                });
            },

            /** Collapses all cards and rows. */
            collapseAll() {
                const content = document.getElementById('saci-content'); if (!content) return;
                content.querySelectorAll('.saci-value-row').forEach(r => r.style.display = 'none');
                content.querySelectorAll('.saci-card').forEach(card => {
                    const c = card.querySelector('.saci-card-content'); const t = card.querySelector('.saci-card-toggle'); if (!c || !t) return;
                    card.classList.remove('is-open'); setTimeout(() => { c.style.display = 'none'; }, 240); t.setAttribute('aria-expanded', 'false');
                });
            }
        };
    };

    // Robust Alpine registration (works regardless of load order)
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        window.Alpine.data('saciBar', window.saciBar);
    } else {
        document.addEventListener('alpine:init', () => {
            Alpine.data('saciBar', window.saciBar);
        });
    }
})();


