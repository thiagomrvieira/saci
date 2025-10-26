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
                    const cardKey = this.getCardKey(card);
                    if (!cardKey) return;
                    card.querySelectorAll('tr[data-saci-var-key]').forEach(row => {
                        const varKey = this.getVarKey(row);
                        if (!varKey) return;
                        const isOpen = this.isRowOpen(cardKey, varKey);
                        if (isOpen) this.restoreRowOpenState(row);
                    });
                });
            },

            /** Helper: get card key. */
            getCardKey(card) {
                return card ? (card.getAttribute('data-saci-card-key') || '') : '';
            },

            /** Helper: get row var key. */
            getVarKey(row) {
                return row ? (row.getAttribute('data-saci-var-key') || '') : '';
            },

            /** Helper: read persisted open state. */
            isRowOpen(cardKey, varKey) {
                return storage.get('saci.var.' + cardKey + '.' + varKey) === '1';
            },

            /** Helper: persist row state. */
            setRowOpen(cardKey, varKey, isOpen) {
                try { storage.set('saci.var.' + cardKey + '.' + varKey, isOpen ? '1' : '0'); } catch (e) {}
            },

            /** Helper: restore a row that should be open (inline or valueRow). */
            restoreRowOpenState(row) {
                const inline = row.querySelector('.saci-dump-inline');
                if (inline) {
                    this.showInlineDump(row, inline);
                    const dumpId = inline.getAttribute('data-dump-id');
                    const requestId = inline.getAttribute('data-request-id');
                    const content = inline.querySelector('.saci-dump-content');
                    if (dumpId && requestId && content && content.childElementCount === 0) {
                        this.loadDumpInto(inline, requestId, dumpId);
                    }
                    return;
                }
                const valueRow = row.nextElementSibling;
                if (!valueRow) return;
                valueRow.style.display = 'table-row';
                const container = valueRow.querySelector('.saci-dump');
                if (!container) return;
                const dumpId = container.getAttribute('data-dump-id');
                const requestId = container.getAttribute('data-request-id');
                const content = container.querySelector('.saci-dump-content');
                if (dumpId && requestId && content && content.childElementCount === 0) {
                    this.loadDumpInto(container, requestId, dumpId);
                }
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
                const inline = row.querySelector('.saci-dump-inline');
                if (inline) {
                    const dumpId = inline.getAttribute('data-dump-id');
                    const requestId = inline.getAttribute('data-request-id');
                    const content = inline.querySelector('.saci-dump-content');
                    const loading = inline.querySelector('.saci-dump-loading');
                    if (dumpId && requestId && content && content.childElementCount === 0) {
                        await this.openInlineAndLoad(row, inline, requestId, dumpId, loading, content);
                        return;
                    } else {
                        this.toggleInlineDump(row, inline);
                        return;
                    }
                }

                const valueRow = row.nextElementSibling; if (!valueRow) return;
                const container = valueRow.querySelector('.saci-dump');
                const hasDump = !!(container && container.getAttribute('data-dump-id'));
                if (hasDump) {
                    const dumpId = container.getAttribute('data-dump-id');
                    const requestId = container.getAttribute('data-request-id');
                    const content = container.querySelector('.saci-dump-content');
                    if (content && content.childElementCount === 0) {
                        // Open row immediately and show preview/loader, then load async for better perceived performance
                        this.toggleVarRow(row);
                        const loading = container.querySelector('.saci-dump-loading');
                        if (loading) loading.style.display = 'block';
                        // Show preview right away if available
                        const previewSpan = row.querySelector('.saci-inline-preview');
                        if (previewSpan && previewSpan.textContent && content.childElementCount === 0) {
                            content.textContent = previewSpan.textContent;
                        }
                        // fire and forget
                        this.loadDumpFromIds(container, requestId, dumpId).catch(() => {});
                        return;
                    }
                }
                this.toggleVarRow(row);
            },

            /** Helper: show inline dump area and hide preview. */
            showInlineDump(row, inline) {
                const previewSpan = row.querySelector('.saci-inline-preview');
                if (previewSpan) previewSpan.style.display = 'none';
                inline.style.display = 'block';
            },

            /** Helper: toggle inline dump visibility and persist state. */
            toggleInlineDump(row, inline) {
                const previewSpan = row.querySelector('.saci-inline-preview');
                const isVisible = inline.style.display !== 'none';
                inline.style.display = isVisible ? 'none' : 'block';
                if (previewSpan) previewSpan.style.display = isVisible ? 'inline' : 'none';
                try {
                    const cardKey = this.getCardKey(row.closest('.saci-card'));
                    const varKey = this.getVarKey(row);
                    if (cardKey && varKey) this.setRowOpen(cardKey, varKey, !isVisible);
                } catch (e) {}
            },

            /** Helper: open inline and load content, persisting state. */
            async openInlineAndLoad(row, inline, requestId, dumpId, loading, content) {
                try {
                    // Open immediately to show loader and avoid perceived lag
                    this.showInlineDump(row, inline);
                    if (loading) loading.style.display = 'block';
                    // Show preview right away if available
                    const previewSpan = row.querySelector('.saci-inline-preview');
                    if (previewSpan && previewSpan.textContent && content && content.childElementCount === 0) {
                        content.textContent = previewSpan.textContent;
                    }
                    const html = await dumps.fetchHtml(requestId, dumpId);
                    content.innerHTML = html;
                    const cardKey = this.getCardKey(row.closest('.saci-card'));
                    const varKey = this.getVarKey(row);
                    if (cardKey && varKey) this.setRowOpen(cardKey, varKey, true);
                } catch (e) {
                    if (content) content.textContent = 'Failed to load dump';
                } finally {
                    if (loading) loading.style.display = 'none';
                }
            },

            /** Loads a dump into the row from ids. @param {HTMLElement} container @param {string} requestId @param {string} dumpId @param {HTMLTableRowElement} row */
            async loadDumpFromIds(container, requestId, dumpId) {
                const loading = container.querySelector('.saci-dump-loading');
                const content = container.querySelector('.saci-dump-content');
                try {
                    const html = await dumps.fetchHtml(requestId, dumpId);
                    if (content) content.innerHTML = html;
                } catch (e) { if (content) content.textContent = 'Failed to load dump'; }
                finally { if (loading) loading.style.display = 'none'; }
            },

            /** Loads dump HTML into a container without toggling rows. */
            async loadDumpInto(container, requestId, dumpId) {
                const loading = container.querySelector('.saci-dump-loading');
                const content = container.querySelector('.saci-dump-content');
                try {
                    if (loading) loading.style.display = 'block';
                    const html = await dumps.fetchHtml(requestId, dumpId);
                    if (content) content.innerHTML = html;
                } catch (e) {
                    if (content) content.textContent = 'Failed to load dump';
                } finally {
                    if (loading) loading.style.display = 'none';
                }
            },

            /** Collapses/expands the bar and persists state. */
            toggle() {
                this.collapsed = !this.collapsed;
                try { storage.set('saci.collapsed', this.collapsed ? '1' : '0'); } catch (e) {}
                if (!this.collapsed) this.reapplyHeight();
            },

            /** Persists current tab selection. */
            saveTab() { try { storage.set('saci.tab', this.tab); } catch (e) {} },

            /** Header click: ignore resize click-throughs, else toggle. */
            onHeaderClick() {
                if (this.isResizing || this.didResize) {
                    this.didResize = false;
                    return;
                }
                this.toggle();
            },

            /** Selects a tab and persists. */
            selectTab(name) {
                this.tab = name;
                this.saveTab();
            },


        };
    };

    // Robust Alpine registration (works regardless of load order). If Alpine is not present,
    // install a lightweight vanilla fallback that removes x-cloak and wires basic tab behavior.
    const registerWithAlpine = () => {
        try {
            if (window.Alpine && typeof window.Alpine.data === 'function') {
                window.Alpine.data('saciBar', window.saciBar);
                return true;
            }
        } catch (e) {}
        return false;
    };

    const setupVanillaFallback = () => {
        try {
            const root = document.getElementById('saci');
            if (!root) return;
            const api = window.saciBar();
            // Shared click-suppression flag to avoid collapsing after drag
            let suppressClickOnce = false;
            // Remove x-cloak elements under #saci so content becomes visible
            root.querySelectorAll('[x-cloak]').forEach(el => { el.removeAttribute('x-cloak'); });

            // Apply persisted height if any
            try {
                const saved = parseInt(storage.get('saci.maxHeightPx') || '', 10);
                if (!isNaN(saved)) {
                    const contentEl = root.querySelector('#saci-content');
                    root.style.maxHeight = saved + 'px';
                    if (contentEl) contentEl.style.maxHeight = 'calc(' + saved + 'px - 36px)';
                }
            } catch (e) {}

            // Tab switching
            const state = { tab: 'views', collapsed: true };
            const setTab = (name) => {
                state.tab = name;
                try { storage.set('saci.tab', name); } catch (e) {}
                togglePanels();
                toggleActiveTabs();
                renderHeaderSummary();
                // Ensure selected panel is un-cloaked
                const current = name === 'views'
                  ? root.querySelector('#saci-tabpanel-views')
                  : name === 'resources'
                    ? root.querySelector('#saci-tabpanel-request')
                    : name === 'route'
                      ? root.querySelector('#saci-tabpanel-route')
                      : root.querySelector('#saci-tabpanel-logs');
                if (current) {
                    current.querySelectorAll('[x-cloak]').forEach(el => { el.removeAttribute('x-cloak'); });
                }
            };
            const togglePanels = () => {
                const panels = {
                    views: root.querySelector('#saci-tabpanel-views'),
                    resources: root.querySelector('#saci-tabpanel-request'),
                    route: root.querySelector('#saci-tabpanel-route'),
                    logs: root.querySelector('#saci-tabpanel-logs')
                };
                Object.keys(panels).forEach(k => {
                    const el = panels[k]; if (!el) return;
                    const isActive = (k === state.tab);
                    el.classList.toggle('saci-panel-active', isActive);
                    // Only toggle display to reduce layout work
                    el.style.display = isActive ? 'block' : 'none';
                });
            };
            const toggleActiveTabs = () => {
                root.querySelectorAll('.saci-tab').forEach(btn => {
                    const id = btn.id || '';
                    const isActive =
                        (id === 'saci-tab-views' && state.tab === 'views') ||
                        (id === 'saci-tab-request' && state.tab === 'resources') ||
                        (id === 'saci-tab-route' && state.tab === 'route') ||
                        (id === 'saci-tab-logs' && state.tab === 'logs');
                    btn.classList.toggle('saci-tab--active', !!isActive);
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
            };

            // Header toggle
            const header = root.querySelector('#saci-header');
            const content = root.querySelector('#saci-content');
            const arrow = root.querySelector('#saci-arrow');
            const setCollapsed = (val) => {
                state.collapsed = !!val;
                root.classList.toggle('saci-collapsed', state.collapsed);
                if (arrow) arrow.textContent = state.collapsed ? '▶' : '▼';
                const contentEl = root.querySelector('#saci-content');
                if (contentEl) contentEl.style.display = state.collapsed ? 'none' : 'block';
                try { storage.set('saci.collapsed', state.collapsed ? '1' : '0'); } catch (e) {}
                renderHeaderSummary();
                if (!state.collapsed) {
                    // Re-apply selected panel visibility on expand
                    togglePanels();
                    const current = state.tab === 'views'
                      ? root.querySelector('#saci-tabpanel-views')
                      : state.tab === 'resources'
                        ? root.querySelector('#saci-tabpanel-request')
                        : state.tab === 'route'
                          ? root.querySelector('#saci-tabpanel-route')
                          : root.querySelector('#saci-tabpanel-logs');
                    if (current) {
                        current.querySelectorAll('[x-cloak]').forEach(el => { el.removeAttribute('x-cloak'); });
                    }
                }
            };
            if (header) {
                // Suppress click toggle when a drag-resize just happened
                header.addEventListener('click', (ev) => {
                    if (suppressClickOnce) { suppressClickOnce = false; return; }
                    setCollapsed(!state.collapsed);
                });
                header.addEventListener('keydown', (ev) => {
                    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setCollapsed(!state.collapsed); }
                });
            }

            // Wire tab buttons
            const map = [
                ['saci-tab-views', 'views'],
                ['saci-tab-request', 'resources'],
                ['saci-tab-route', 'route'],
                ['saci-tab-logs', 'logs'],
            ];
            map.forEach(([id, name]) => {
                const el = root.querySelector('#' + id);
                if (el) el.addEventListener('click', (e) => { e.stopPropagation(); setTab(name); });
            });

            // Wire card toggles (so clicking a card header opens/closes)
            const attachCardListeners = () => {
                root.querySelectorAll('#saci-content .saci-card').forEach(card => {
                    const toggle = card.querySelector('.saci-card-toggle');
                    if (!toggle) return;
                    const isInteractive = (toggle.getAttribute('role') === 'button');
                    // Avoid duplicate listeners
                    if (toggle.__saci_bound) return;
                    toggle.__saci_bound = true;
                    if (isInteractive) {
                        toggle.addEventListener('click', (ev) => { ev.stopPropagation(); api.toggleCard(card); });
                        toggle.addEventListener('keydown', (ev) => {
                            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); api.toggleCard(card); }
                        });
                    } else {
                        // Non-interactive cards (e.g., Request/Route) should stay open
                        const contentEl = card.querySelector('.saci-card-content');
                        if (contentEl) contentEl.style.display = 'block';
                        card.classList.add('is-open');
                    }

                    // Restore persisted open/closed state only for interactive cards
                    if (isInteractive) {
                        try {
                            const key = card.getAttribute('data-saci-card-key') || '';
                            if (!key) return;
                            const persisted = storage.get('saci.card.' + key);
                            const contentEl = card.querySelector('.saci-card-content');
                            if (!contentEl) return;
                            if (persisted === '1') {
                                contentEl.style.display = 'block';
                                card.classList.add('is-open');
                                toggle.setAttribute('aria-expanded', 'true');
                            } else if (persisted === '0') {
                                card.classList.remove('is-open');
                                toggle.setAttribute('aria-expanded', 'false');
                                contentEl.style.display = 'none';
                            }
                        } catch (e) {}
                    }
                });
            };

            // Wire variable rows (so clicking a row loads/toggles dumps)
            const attachVarRowListeners = () => {
                root.querySelectorAll('#saci-content tr[data-saci-var-key]').forEach(row => {
                    if (row.__saci_bound) return;
                    row.__saci_bound = true;
                    row.addEventListener('click', (ev) => { ev.stopPropagation(); api.onVarRowClick(row); });
                    row.setAttribute('tabindex', '0');
                    row.addEventListener('keydown', (ev) => {
                        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); api.onVarRowClick(row); }
                    });
                });
            };

            // Restore variable rows open state from storage
            const restoreVarRowsFromStorage = async () => {
                root.querySelectorAll('#saci-content .saci-card').forEach(card => {
                    const cardKey = card.getAttribute('data-saci-card-key') || '';
                    if (!cardKey) return;
                    card.querySelectorAll('tr[data-saci-var-key]').forEach(row => {
                        const varKey = row.getAttribute('data-saci-var-key') || '';
                        if (!varKey) return;
                        try {
                            const isOpen = storage.get('saci.var.' + cardKey + '.' + varKey) === '1';
                            if (!isOpen) return;
                            // Prefer inline dump if available
                            const inline = row.querySelector('.saci-dump-inline');
                            if (inline) {
                                const content = inline.querySelector('.saci-dump-content');
                                const dumpId = inline.getAttribute('data-dump-id');
                                const requestId = inline.getAttribute('data-request-id');
                                const preview = row.querySelector('.saci-inline-preview');
                                if (preview && preview.style) preview.style.display = 'none';
                                inline.style.display = 'block';
                                if (dumpId && requestId && content && content.childElementCount === 0) {
                                    api.loadDumpInto(inline, requestId, dumpId);
                                }
                                return;
                            }
                            // Fallback: open value row
                            const valueRow = row.nextElementSibling;
                            if (!valueRow) return;
                            valueRow.style.display = 'table-row';
                            const container = valueRow.querySelector('.saci-dump');
                            if (container) {
                                const dumpId = container.getAttribute('data-dump-id');
                                const requestId = container.getAttribute('data-request-id');
                                const content = container.querySelector('.saci-dump-content');
                                if (dumpId && requestId && content && content.childElementCount === 0) {
                                    api.loadDumpInto(container, requestId, dumpId);
                                }
                            }
                        } catch (e) {}
                    });
                });
            };

            // Restore persisted state
            try {
                const savedTab = storage.get('saci.tab');
                if (savedTab) state.tab = savedTab;
            } catch (e) {}
            try { setCollapsed(storage.get('saci.collapsed') === '1'); } catch (e) {}

            togglePanels();
            toggleActiveTabs();
            // Uncloak initially selected panel (respect restored tab)
            const initialPanel = state.tab === 'views'
              ? root.querySelector('#saci-tabpanel-views')
              : state.tab === 'resources'
                ? root.querySelector('#saci-tabpanel-request')
                : state.tab === 'route'
                  ? root.querySelector('#saci-tabpanel-route')
                  : root.querySelector('#saci-tabpanel-logs');
            if (initialPanel) initialPanel.querySelectorAll('[x-cloak]').forEach(el => { el.removeAttribute('x-cloak'); });

            // Header summaries (views/request) in vanilla mode
            const viewsDisplay = (root.getAttribute('data-views-display') || '');
            const viewsClass = (root.getAttribute('data-views-class') || '');
            const viewsTooltip = (root.getAttribute('data-views-tooltip') || '');
            const requestDisplay = (root.getAttribute('data-request-display') || '');
            const requestClass = (root.getAttribute('data-request-class') || '');
            const requestTooltip = (root.getAttribute('data-request-tooltip') || '');
            const totalViews = (root.getAttribute('data-total-views') || '');
            const methodStr = (root.getAttribute('data-method') || '').trim();
            const uriStr = (root.getAttribute('data-uri') || '').trim();
            const controls = root.querySelector('#saci-controls');
            const renderHeaderSummary = () => {
                if (!controls) return;
                const expanded = !state.collapsed;
                const right = controls.querySelector('#saci-controls-buttons');
                const version = controls.querySelector('#saci-controls-version');
                if (expanded && right) {
                    if (state.tab === 'views' && viewsDisplay) {
                        right.innerHTML = '<div class="saci-summary" style="margin:0;"><div class="saci-summary-right">' +
                          (totalViews ? (totalViews + ' views loaded in: ') : '') +
                          '<strong class="' + viewsClass + '" ' + (viewsTooltip ? ('data-saci-tooltip="' + viewsTooltip.replace(/"/g, '&quot;') + '"') : '') + '>' + viewsDisplay + '</strong></div></div>';
                    } else if (state.tab === 'resources' && requestDisplay) {
                        right.innerHTML = '<div class="saci-summary" style="margin:0;"><div class="saci-summary-right">Response time: ' +
                          '<strong class="' + requestClass + '" ' + (requestTooltip ? ('data-saci-tooltip="' + requestTooltip.replace(/"/g, '&quot;') + '"') : '') + '>' + requestDisplay + '</strong></div></div>';
                    } else if (state.tab === 'route') {
                        right.innerHTML = '<div class="saci-summary" style="margin:0;"><div class="saci-summary-left">' + methodStr + ' ' + uriStr + '</div></div>';
                    } else {
                        right.innerHTML = '';
                    }
                }
                if (!expanded && version) {
                    if (state.tab === 'views' && viewsDisplay) {
                        version.innerHTML = (totalViews ? (totalViews + ' views loaded in: ') : 'Views: ') + '<strong class="' + viewsClass + '" ' + (viewsTooltip ? ('data-saci-tooltip="' + viewsTooltip.replace(/"/g, '&quot;') + '"') : '') + '>' + viewsDisplay + '</strong>';
                    } else if (state.tab === 'resources' && requestDisplay) {
                        version.innerHTML = 'Response time: <strong class="' + requestClass + '" ' + (requestTooltip ? ('data-saci-tooltip="' + requestTooltip.replace(/"/g, '&quot;') + '"') : '') + '>' + requestDisplay + '</strong>';
                    } else if (state.tab === 'route') {
                        version.innerHTML = methodStr + ' ' + uriStr;
                    } else {
                        version.innerHTML = '';
                    }
                }
            };

            renderHeaderSummary();

            // Drag-to-resize in vanilla mode
            if (header) {
                let startY = 0;
                let startHeight = 0;
                let isResizing = false;
                let pending = false;
                const MIN = 120;
                const MAX = Math.round((window.innerHeight || document.documentElement.clientHeight) * 0.9);

                const applyHeight = (px) => {
                    const contentEl = root.querySelector('#saci-content');
                    root.style.maxHeight = px + 'px';
                    if (contentEl) contentEl.style.maxHeight = 'calc(' + px + 'px - 36px)';
                    try { storage.set('saci.maxHeightPx', String(px)); } catch (e) {}
                };

                header.addEventListener('mousedown', (e) => {
                    if (root.classList.contains('saci-collapsed')) return;
                    pending = true;
                    startY = e.clientY;
                    startHeight = root.getBoundingClientRect().height;
                    const threshold = 3;

                    const onMove = (ev) => {
                        if (!isResizing) return;
                        const delta = startY - ev.clientY;
                        applyHeight(Math.max(MIN, Math.min(startHeight + delta, MAX)));
                        ev.preventDefault();
                    };
                    const onUp = () => {
                        if (!isResizing && pending) { pending = false; }
                        if (!isResizing) return;
                        isResizing = false;
                        document.body.style.userSelect = '';
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                        // prevent the subsequent click from toggling
                        suppressClickOnce = true;
                        setTimeout(() => { suppressClickOnce = false; }, 0);
                    };
                    const detect = (ev) => {
                        if (!pending) return;
                        if (Math.abs(ev.clientY - startY) >= threshold) {
                            pending = false;
                            isResizing = true;
                            document.body.style.userSelect = 'none';
                            document.addEventListener('mousemove', onMove);
                            document.addEventListener('mouseup', onUp);
                            onMove(ev);
                        }
                    };
                    document.addEventListener('mousemove', detect);
                    document.addEventListener('mouseup', () => {
                        document.removeEventListener('mousemove', detect);
                    }, { once: true });
                });
            }
            attachCardListeners();
            attachVarRowListeners();
            restoreVarRowsFromStorage();

            // Vanilla tooltips (since Alpine is not present)
            const ensurePopover = () => {
                let el = document.getElementById('saci-popover');
                if (!el) {
                    el = document.createElement('div');
                    el.id = 'saci-popover';
                    el.style.position = 'fixed';
                    el.style.display = 'none';
                    el.style.pointerEvents = 'none';
                    document.body.appendChild(el);
                }
                return el;
            };
            const showTipFor = (target) => {
                if (!target) return;
                const text = target.getAttribute('data-saci-tooltip') || '';
                if (!text) return;
                const pop = ensurePopover();
                pop.textContent = text;
                const rect = target.getBoundingClientRect();
                const vw = window.innerWidth || document.documentElement.clientWidth;
                const vh = window.innerHeight || document.documentElement.clientHeight;
                // First show hidden to measure size accurately
                pop.style.visibility = 'hidden';
                pop.style.display = 'block';
                const popRect = pop.getBoundingClientRect();
                const pad = 8;
                const preferBelow = (vh - rect.bottom) >= (rect.top);
                let top;
                if (preferBelow) {
                    // Place below, out of the way so it doesn't cover the trigger
                    top = rect.bottom + pad;
                    pop.setAttribute('data-placement', 'bottom');
                } else {
                    // Place above, fully outside the trigger
                    top = rect.top - popRect.height - pad;
                    pop.setAttribute('data-placement', 'top');
                }
                // Center horizontally then clamp
                let left = rect.left + (rect.width - popRect.width) / 2;
                left = Math.max(pad, Math.min(left, vw - popRect.width - pad));
                pop.style.left = Math.round(left) + 'px';
                pop.style.top = Math.round(Math.max(pad, top)) + 'px';
                // Make visible after positioning
                pop.style.visibility = 'visible';
            };
            const hideTip = () => {
                const pop = document.getElementById('saci-popover');
                if (pop) pop.style.display = 'none';
            };
            const tooltipSelector = '[data-saci-tooltip]';
            root.addEventListener('mouseover', (e) => {
                const t = e.target && e.target.closest ? e.target.closest(tooltipSelector) : null;
                if (t && root.contains(t)) showTipFor(t);
            });
            root.addEventListener('mouseout', (e) => {
                const from = e.target && e.target.closest ? e.target.closest(tooltipSelector) : null;
                if (from) hideTip();
            });
            root.addEventListener('focusin', (e) => {
                const t = e.target && e.target.closest ? e.target.closest(tooltipSelector) : null;
                if (t && root.contains(t)) showTipFor(t);
            });
            root.addEventListener('focusout', (e) => {
                const from = e.target && e.target.closest ? e.target.closest(tooltipSelector) : null;
                if (from) hideTip();
            });
        } catch (e) {}
    };

    if (!registerWithAlpine()) {
        // Alpine not present; run vanilla fallback at DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupVanillaFallback);
        } else {
            setupVanillaFallback();
        }
    }
})();


