 /* Saci Debug Bar JS (vanilla) */
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
     * @type {{fetchHtml:(requestId:string,dumpId:string)=>Promise<string>, fetchLateLogs:(requestId:string)=>Promise<object>}}
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
        },
        /** Fetch late logs that were collected after response was sent (terminable middleware, shutdown handlers, etc). */
        async fetchLateLogs(requestId) {
            const url = `/__saci/late-logs/${encodeURIComponent(requestId)}`;
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            if (!response.ok) {
                throw new Error('Failed to load late logs');
            }
            return await response.json();
        }
    };

    /** Clamp a number between bounds. @param {number} v @param {number} lo @param {number} hi @returns {number} */
    const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

    /**
     * Virtual Scrolling Module
     * Handles efficient rendering of large log lists (>50 items)
     */
    const virtualScroll = {
        // Configuration
        THRESHOLD: 50,
        BUFFER_SIZE: 10,
        ESTIMATED_ROW_HEIGHT: 40,

        // State
        enabled: false,
        container: null,
        tbody: null,
        allRows: [],
        visibleRows: [],
        scrollTop: 0,
        containerHeight: 0,

        /** Initialize virtual scrolling if needed */
        init(tbody) {
            this.tbody = tbody;
            if (!tbody) return false;

            this.allRows = Array.from(tbody.querySelectorAll('tr[data-saci-var-key]'));

            // Only enable if we have more than threshold
            if (this.allRows.length <= this.THRESHOLD) {
                this.enabled = false;
                return false;
            }

            // Calculate average row height from first few rows
            this.calculateRowHeight();

            this.enabled = true;
            this.setupContainer();
            this.render();
            return true;
        },

        /** Calculate average row height from sample */
        calculateRowHeight() {
            const sampleSize = Math.min(10, this.allRows.length);
            let totalHeight = 0;

            for (let i = 0; i < sampleSize; i++) {
                const row = this.allRows[i];
                if (row) {
                    totalHeight += row.offsetHeight || this.ESTIMATED_ROW_HEIGHT;
                }
            }

            this.ESTIMATED_ROW_HEIGHT = Math.max(30, Math.ceil(totalHeight / sampleSize));
        },

        /** Setup virtual scroll container */
        setupContainer() {
            const table = this.tbody.closest('table');
            if (!table || table.parentElement.classList.contains('saci-table-logs-virtual-container')) return;

            // Wrap table in virtual container
            const container = document.createElement('div');
            container.className = 'saci-table-logs-virtual-container';
            table.parentElement.insertBefore(container, table);
            container.appendChild(table);

            this.container = container;
            table.classList.add('saci-virtual');

            // Add scroll listener with throttle
            let rafId = null;
            container.addEventListener('scroll', () => {
                if (rafId) return;
                rafId = requestAnimationFrame(() => {
                    this.handleScroll();
                    rafId = null;
                });
            });

            this.containerHeight = container.clientHeight;
        },

        /** Handle scroll event */
        handleScroll() {
            if (!this.container) return;
            this.scrollTop = this.container.scrollTop;
            this.render();
        },

        /** Calculate visible range */
        getVisibleRange() {
            const start = Math.floor(this.scrollTop / this.ESTIMATED_ROW_HEIGHT);
            const visibleCount = Math.ceil(this.containerHeight / this.ESTIMATED_ROW_HEIGHT);

            // Add buffer
            const startIndex = Math.max(0, start - this.BUFFER_SIZE);
            const endIndex = Math.min(this.visibleRows.length, start + visibleCount + this.BUFFER_SIZE);

            return { startIndex, endIndex };
        },

        /** Render visible rows */
        render() {
            if (!this.enabled || !this.tbody) return;

            const { startIndex, endIndex } = this.getVisibleRange();

            // Clear tbody
            this.tbody.innerHTML = '';

            // Add top spacer
            if (startIndex > 0) {
                const spacer = document.createElement('tr');
                spacer.className = 'saci-table-logs-virtual-spacer';
                spacer.style.height = (startIndex * this.ESTIMATED_ROW_HEIGHT) + 'px';
                this.tbody.appendChild(spacer);
            }

            // Render visible rows
            for (let i = startIndex; i < endIndex; i++) {
                const row = this.visibleRows[i];
                if (row) this.tbody.appendChild(row);
            }

            // Add bottom spacer
            const remaining = this.visibleRows.length - endIndex;
            if (remaining > 0) {
                const spacer = document.createElement('tr');
                spacer.className = 'saci-table-logs-virtual-spacer';
                spacer.style.height = (remaining * this.ESTIMATED_ROW_HEIGHT) + 'px';
                this.tbody.appendChild(spacer);
            }

            // Notify that rows have been rendered (for event listener reattachment)
            if (this.onRender) this.onRender();
        },

        /** Set callback for when rows are rendered */
        setOnRender(callback) {
            this.onRender = callback;
        },

        /** Update visible rows (called after filtering) */
        updateVisibleRows(rows) {
            this.visibleRows = rows;
            if (this.enabled) {
                this.render();
            }
        },

        /** Disable virtual scrolling */
        disable() {
            this.enabled = false;
            if (this.tbody && this.allRows.length > 0) {
                this.tbody.innerHTML = '';
                this.allRows.forEach(row => this.tbody.appendChild(row));
            }
        }
    };

    /**
     * Log Filtering Module (SOLID: Single Responsibility)
     * Handles all log filtering logic with intelligent search, regex, and level filtering.
     */
    const logFilters = {
        // Constants
        ERROR_LEVELS: ['emergency', 'alert', 'critical', 'error'],
        FUZZY_PROXIMITY: 15,
        UNIQUE_RATIO_THRESHOLD: 0.4,
        QUERY_LENGTH_LIMIT: 20,

        // DOM elements
        searchInput: null,
        clearBtn: null,
        levelSelect: null,
        timeFilter: null,
        errorsOnlyCheckbox: null,
        regexCheckbox: null,
        statsText: null,
        logRows: null,

        /** Initialize log filtering functionality. */
        init() {
            // Cache DOM elements (Object.assign for cleaner code)
            Object.assign(this, {
                searchInput: document.getElementById('saci-log-search'),
                clearBtn: document.getElementById('saci-log-search-clear'),
                levelSelect: document.getElementById('saci-log-level-filter'),
                timeFilter: document.getElementById('saci-log-time-filter'),
                errorsOnlyCheckbox: document.getElementById('saci-log-errors-only'),
                regexCheckbox: document.getElementById('saci-log-regex'),
                statsText: document.getElementById('saci-log-stats-text')
            });

            // Early return if essential elements missing
            if (!this.searchInput || !this.levelSelect) return;

            const logsTable = document.querySelector('.saci-table-logs tbody');
            if (!logsTable) return;

            this.logRows = logsTable.querySelectorAll('tr[data-saci-var-key]');

            // Initialize virtual scrolling if needed
            virtualScroll.init(logsTable);

            this.restoreFilters();
            this.attachEventListeners();
            this.applyFilters();
        },

        /** Attach event listeners with DRY helper. */
        attachEventListeners() {
            const onFilterChange = () => {
                this.applyFilters();
                this.persistFilters();
            };

            // Search with clear button
            this.searchInput?.addEventListener('input', () => {
                onFilterChange();
                this.updateClearButton();
            });

            this.clearBtn?.addEventListener('click', () => {
                this.searchInput.value = '';
                onFilterChange();
                this.updateClearButton();
                this.searchInput.focus();
            });

            // Level and time filters
            this.levelSelect?.addEventListener('change', onFilterChange);
            this.timeFilter?.addEventListener('input', onFilterChange);
            this.regexCheckbox?.addEventListener('change', onFilterChange);

            // Errors-only toggle (clears level filter)
            this.errorsOnlyCheckbox?.addEventListener('change', () => {
                if (this.errorsOnlyCheckbox.checked) {
                    this.levelSelect.value = '';
                }
                onFilterChange();
            });
        },

        /** Apply all active filters to log rows. */
        applyFilters() {
            if (!this.logRows) return;

            const filters = {
                searchText: this.searchInput?.value.trim() || '',
                levelFilter: this.levelSelect?.value.toLowerCase() || '',
                timeFilterText: this.timeFilter?.value.trim() || '',
                errorsOnly: this.errorsOnlyCheckbox?.checked || false,
                useRegex: this.regexCheckbox?.checked || false,
                errorLevels: this.ERROR_LEVELS
            };

            if (virtualScroll.enabled) {
                // Virtual scrolling mode: filter rows and pass to virtual scroller
                const visibleRows = [];
                this.logRows.forEach(row => {
                    if (this.shouldShowRow(row, filters)) {
                        visibleRows.push(row);
                    }
                });
                virtualScroll.updateVisibleRows(visibleRows);
                this.updateStats(visibleRows.length, this.logRows.length);
            } else {
                // Standard mode: show/hide with CSS
                let visibleCount = 0;
                this.logRows.forEach(row => {
                    const shouldShow = this.shouldShowRow(row, filters);
                    if (shouldShow) {
                        this.showLogRow(row);
                        visibleCount++;
                    } else {
                        this.hideLogRow(row);
                    }
                });
                this.updateStats(visibleCount, this.logRows.length);
            }
        },

        /**
         * Determine if a row should be visible based on filters.
         * @param {HTMLTableRowElement} row
         * @param {{searchText:string, levelFilter:string, timeFilterText:string, errorsOnly:boolean, useRegex:boolean, errorLevels:string[]}} filters
         * @returns {boolean}
         */
        shouldShowRow(row, { searchText, levelFilter, timeFilterText, errorsOnly, useRegex, errorLevels }) {
            // Extract row data (pure functions for testability)
            const getText = (selector) => row.querySelector(selector)?.textContent.trim() || '';

            const level = getText('.saci-badge-level').toLowerCase() || 'info';
            const time = getText('.saci-col-time');
            const searchableText = `${getText('.saci-col-message .saci-inline-preview')} ${getText('.saci-col-context .saci-inline-preview')}`.toLowerCase();

            // Chain filters (early return for performance)
            return !(
                (errorsOnly && !errorLevels.includes(level)) ||
                (levelFilter && level !== levelFilter) ||
                (timeFilterText && !this.matchesTime(time, timeFilterText)) ||
                (searchText && !this.matchesSearch(searchableText, searchText, useRegex))
            );
        },

        /**
         * Check if text matches search query (with regex and fuzzy search support).
         * @param {string} text - Text to search in
         * @param {string} query - Search query
         * @param {boolean} useRegex - Whether to use regex
         * @returns {boolean}
         */
        matchesSearch(text, query, useRegex) {
            if (!query) return true;

            if (useRegex) {
                try {
                    const regex = new RegExp(query, 'i');
                    return regex.test(text);
                } catch (e) {
                    // Invalid regex, fallback to simple search
                    return text.includes(query.toLowerCase());
                }
            } else {
                // Intelligent search: substring or smart fuzzy
                return this.intelligentMatch(text, query.toLowerCase());
            }
        },

        /**
         * Intelligent matching: substring or smart fuzzy (avoids false positives).
         * @param {string} text - Text to search in
         * @param {string} query - Search query (lowercase)
         * @returns {boolean}
         */
        intelligentMatch(text, query) {
            if (text.includes(query)) return true;
            if (query.length > this.QUERY_LENGTH_LIMIT || this.isRepetitiveQuery(query)) return false;
            return this.fuzzyMatch(text, query);
        },

        /**
         * Check if query is repetitive (e.g., "aaaa" has low character diversity).
         * @param {string} query
         * @returns {boolean}
         */
        isRepetitiveQuery(query) {
            return query.length >= 3 && (new Set(query).size / query.length) < this.UNIQUE_RATIO_THRESHOLD;
        },

        /**
         * Fuzzy match: all query chars appear in order with proximity check.
         * @param {string} text
         * @param {string} query
         * @returns {boolean}
         */
        fuzzyMatch(text, query) {
            let queryIndex = 0;
            let lastMatchIndex = -1;

            for (let i = 0; i < text.length && queryIndex < query.length; i++) {
                if (text[i] === query[queryIndex] &&
                    (lastMatchIndex === -1 || i - lastMatchIndex < this.FUZZY_PROXIMITY)) {
                    queryIndex++;
                    lastMatchIndex = i;
                }
            }

            return queryIndex === query.length;
        },

        /**
         * Match time with wildcard support (e.g., "14:3*", "*:45").
         * @param {string} timeText
         * @param {string} filterText
         * @returns {boolean}
         */
        matchesTime(timeText, filterText) {
            if (!filterText || !timeText) return !filterText;

            const lower = filterText.toLowerCase();
            if (timeText.toLowerCase().includes(lower)) return true;

            try {
                const pattern = lower.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
                return new RegExp(`^${pattern}$`, 'i').test(timeText);
            } catch {
                return false;
            }
        },

        /**
         * Show a log row with smooth fade-in animation.
         * @param {HTMLTableRowElement} row
         */
        showLogRow(row) {
            // Remove all hiding/hidden classes
            row.classList.remove('saci-log-hidden', 'saci-log-hiding');

            // Force reflow to ensure display: none is removed before animating
            void row.offsetHeight;

            // Fade in (opacity transition)
            requestAnimationFrame(() => {
                row.style.opacity = '1';
            });
        },

        /**
         * Hide a log row with smooth fade-out animation, then remove from layout.
         * Uses the two-phase approach: fade out → display none (no empty gaps!)
         * @param {HTMLTableRowElement} row
         */
        hideLogRow(row) {
            // Already hidden? Skip
            if (row.classList.contains('saci-log-hidden')) return;

            // Check if user prefers reduced motion
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (prefersReducedMotion) {
                // Skip animation, hide immediately
                row.classList.add('saci-log-hidden');
                return;
            }

            // Phase 1: Start fade out animation
            row.classList.add('saci-log-hiding');

            // Phase 2: After animation completes, remove from layout (no gaps!)
            const onTransitionEnd = (e) => {
                // Only respond to opacity transition on this specific row
                if (e.propertyName === 'opacity' && e.target === row) {
                    row.classList.remove('saci-log-hiding');
                    row.classList.add('saci-log-hidden');
                    row.removeEventListener('transitionend', onTransitionEnd);
                }
            };

            row.addEventListener('transitionend', onTransitionEnd);

            // Fallback: ensure hiding completes even if transitionend doesn't fire
            setTimeout(() => {
                if (row.classList.contains('saci-log-hiding')) {
                    row.classList.remove('saci-log-hiding');
                    row.classList.add('saci-log-hidden');
                    row.removeEventListener('transitionend', onTransitionEnd);
                }
            }, 250); // Slightly longer than transition duration (180ms)
        },

        /**
         * Update the stats display.
         * @param {number} visible - Number of visible logs
         * @param {number} total - Total number of logs
         */
        updateStats(visible, total) {
            if (!this.statsText) return;

            if (visible === total) {
                this.statsText.textContent = `${total} ${total === 1 ? 'log' : 'logs'}`;
            } else {
                this.statsText.textContent = `${total} ${total === 1 ? 'log' : 'logs'} (showing ${visible} filtered)`;
            }
        },

        /** Update clear button visibility. */
        updateClearButton() {
            if (!this.clearBtn || !this.searchInput) return;

            if (this.searchInput.value.trim()) {
                this.clearBtn.classList.remove('saci-hidden');
            } else {
                this.clearBtn.classList.add('saci-hidden');
            }
        },

        /** Persist filter state to localStorage. */
        persistFilters() {
            try {
                const state = {
                    search: this.searchInput ? this.searchInput.value : '',
                    level: this.levelSelect ? this.levelSelect.value : '',
                    time: this.timeFilter ? this.timeFilter.value : '',
                    errorsOnly: this.errorsOnlyCheckbox ? this.errorsOnlyCheckbox.checked : false,
                    regex: this.regexCheckbox ? this.regexCheckbox.checked : false
                };
                storage.set('saci.logFilters', JSON.stringify(state));
            } catch (e) {
                // Silently fail
            }
        },

        /** Restore filter state from localStorage. */
        restoreFilters() {
            try {
                const saved = storage.get('saci.logFilters');
                if (!saved) return;

                const state = JSON.parse(saved);

                if (this.searchInput && state.search) {
                    this.searchInput.value = state.search;
                }
                if (this.levelSelect && state.level) {
                    this.levelSelect.value = state.level;
                }
                if (this.timeFilter && state.time) {
                    this.timeFilter.value = state.time;
                }
                if (this.errorsOnlyCheckbox && state.errorsOnly) {
                    this.errorsOnlyCheckbox.checked = true;
                }
                if (this.regexCheckbox && state.regex) {
                    this.regexCheckbox.checked = true;
                }

                this.updateClearButton();
            } catch (e) {
                // Silently fail
            }
        }
    };

    /** Component factory for the Saci bar (reused by vanilla init). @returns {object} */
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
                // Fetch late logs (from terminable middleware, shutdown handlers, etc)
                this.fetchAndRenderLateLogs();
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
                // Check for inline dumps (logs have both message and context)
                const inlines = row.querySelectorAll('.saci-dump-inline');
                if (inlines.length > 0) {
                    // Restore ALL inline dumps in the row (message + context)
                    inlines.forEach(inline => {
                        const cell = inline.closest('.saci-preview');
                        const preview = cell ? cell.querySelector('.saci-inline-preview') : null;
                        if (preview) preview.style.display = 'none';
                        inline.classList.remove('saci-hidden');
                        inline.style.display = 'block';

                        const dumpId = inline.getAttribute('data-dump-id');
                        const requestId = inline.getAttribute('data-request-id');
                        const content = inline.querySelector('.saci-dump-content');
                        const isEmpty = content && content.childElementCount === 0 && !content.textContent.trim();

                        // Show preview text immediately if content is empty
                        if (isEmpty && preview && preview.textContent) {
                            content.textContent = preview.textContent;
                        }

                        // Load full dump if available
                        if (dumpId && requestId && isEmpty) {
                            this.loadDumpInto(inline, requestId, dumpId);
                        }
                    });
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
                const isEmpty = content && content.childElementCount === 0 && !content.textContent.trim();
                if (dumpId && requestId && isEmpty) {
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
                    saciEl.classList.remove('saci-no-transition');
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
                            saciEl.classList.add('saci-no-transition');
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

            /** Fetch and render late logs (from terminable middleware, shutdown, etc) */
            async fetchAndRenderLateLogs() {
                try {
                    // Get request ID from the bar
                    const requestId = this.$root.getAttribute('data-saci-request-id');
                    if (!requestId) {
                        console.debug('Saci: No request ID found, skipping late logs fetch');
                        return;
                    }

                    // Single optimized fetch with shorter delay (250ms is enough for most cases)
                    await new Promise(resolve => setTimeout(resolve, 250));

                    // Fetch late logs
                    const data = await dumps.fetchLateLogs(requestId);
                    if (!data || !data.logs || data.logs.length === 0) {
                        console.debug('Saci: No late logs found');
                        return;
                    }

                    // Find the logs table tbody
                    const logsTable = this.$root.querySelector('.saci-table-logs tbody');
                    if (!logsTable) {
                        console.warn('Saci: Logs table not found in DOM');
                        return;
                    }

                    console.debug(`Saci: Rendering ${data.logs.length} late logs`);

                    // Remove empty state message if present
                    const emptyState = logsTable.querySelector('.saci-empty-state');
                    if (emptyState) {
                        emptyState.parentElement.remove();
                    }

                    // Batch DOM updates using DocumentFragment for better performance
                    const fragment = document.createDocumentFragment();

                    // Create all rows in memory first
                    data.logs.forEach((log, idx) => {
                        const rowKey = 'log-late-' + idx;
                        const level = (log.level || 'info').toLowerCase();

                        // Create the main row
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-saci-var-key', rowKey);
                        tr.classList.add('saci-late-log');

                        // Level column (badge)
                        const tdLevel = document.createElement('td');
                        tdLevel.className = 'saci-var-name saci-col-level';
                        const levelBadge = document.createElement('span');
                        levelBadge.className = 'saci-badge saci-badge-level';
                        levelBadge.textContent = level.toUpperCase();
                        tdLevel.appendChild(levelBadge);

                        // Time column
                        const tdTime = document.createElement('td');
                        tdTime.className = 'saci-var-type saci-col-time';
                        tdTime.textContent = log.time || '';

                        // Message column
                        const tdMessage = document.createElement('td');
                        tdMessage.className = 'saci-preview saci-col-message';
                        const msgPreview = document.createElement('span');
                        msgPreview.className = 'saci-inline-preview';
                        msgPreview.textContent = log.message_preview || '';
                        tdMessage.appendChild(msgPreview);

                        // Context column
                        const tdContext = document.createElement('td');
                        tdContext.className = 'saci-preview saci-col-context';
                        const ctxPreview = document.createElement('span');
                        ctxPreview.className = 'saci-inline-preview';
                        ctxPreview.textContent = log.context_preview || '';
                        tdContext.appendChild(ctxPreview);

                        tr.appendChild(tdLevel);
                        tr.appendChild(tdTime);
                        tr.appendChild(tdMessage);
                        tr.appendChild(tdContext);

                        fragment.appendChild(tr);
                    });

                    // Single DOM update (batched) - triggers only one reflow
                    logsTable.appendChild(fragment);

                    // Update badge count if logs tab badge exists
                    const logsTab = this.$root.querySelector('[data-saci-tab="logs"]');
                    if (logsTab) {
                        const badge = logsTab.querySelector('.saci-badge');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent || '0', 10);
                            badge.textContent = String(currentCount + data.logs.length);
                        }
                    }

                    // Reapply log filters to include new late logs
                    if (logFilters.logRows) {
                        logFilters.logRows = logsTable.querySelectorAll('tr[data-saci-var-key]');

                        // Reinitialize virtual scrolling if threshold crossed
                        if (!virtualScroll.enabled && logFilters.logRows.length > virtualScroll.THRESHOLD) {
                            virtualScroll.init(logsTable);
                        } else if (virtualScroll.enabled) {
                            virtualScroll.allRows = Array.from(logFilters.logRows);
                        }

                        logFilters.applyFilters();
                    }
                } catch (e) {
                    // Silently fail if late logs can't be fetched (expected in many cases)
                    console.debug('Saci: Could not fetch late logs', e);
                }
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
                if (isHidden) {
                    this.adjustHeightToFit(row);
                }
            },

            /** Determine if a row click should be ignored (click inside dump content/toggles). */
            shouldIgnoreRowClick(evt) {
                try {
                    const target = evt && evt.target ? evt.target : null;
                    if (!target || !target.closest) return false;
                    // Do NOT ignore clicks on inline container itself; only ignore deep dumps and explicit stops
                    const ignoreSelector = '.saci-dump, .saci-dump-content, .sf-dump, .saci-inline-preview, [data-saci-stop-row-toggle]';
                    return !!target.closest(ignoreSelector);
                } catch (e) { return false; }
            },

            /** Open the specific inline dump tied to a preview span. */
            openInlineFromPreview(previewSpan) {
                try {
                    const row = previewSpan.closest('tr[data-saci-var-key]');
                    if (!row) return;
                    const inlines = Array.from(row.querySelectorAll('.saci-preview .saci-dump-inline'));
                    if (inlines.length === 0) return;
                    const anyVisible = inlines.some(el => el.style.display !== 'none');
                    if (anyVisible) {
                        // Close all
                        this.closeAllInlinesInRow(row);
                    } else {
                        // Open all
                        this.openAllInlinesInRow(row);
                    }
                } catch (e) {}
            },

            /** Open both message and context inline dumps in the row. */
            openAllInlinesInRow(row) {
                const inlines = row.querySelectorAll('.saci-preview .saci-dump-inline');
                inlines.forEach(inline => {
                    const cell = inline.closest('.saci-preview');
                    const preview = cell ? cell.querySelector('.saci-inline-preview') : null;
                    if (preview) preview.style.display = 'none';
                    inline.classList.remove('saci-hidden');
                    inline.style.display = 'block';
                    const dumpId = inline.getAttribute('data-dump-id');
                    const requestId = inline.getAttribute('data-request-id');
                    const content = inline.querySelector('.saci-dump-content');
                    const loading = inline.querySelector('.saci-dump-loading');
                    // Check if content is truly empty (no HTML elements AND no text)
                    const isEmpty = content && content.childElementCount === 0 && !content.textContent.trim();
                    // Always ensure there is visible content: use preview text immediately if empty
                    if (isEmpty && preview && preview.textContent) {
                        content.textContent = preview.textContent;
                    }
                    // If a dump is available and content is empty, load it async and replace the preview text
                    if (dumpId && requestId && isEmpty) {
                        if (loading) loading.classList.remove('saci-hidden');
                        this.loadDumpInto(inline, requestId, dumpId).finally(() => { if (loading) loading.classList.add('saci-hidden'); });
                    }
                });
                try {
                    const cardKey = this.getCardKey(row.closest('.saci-card'));
                    const varKey = this.getVarKey(row);
                    if (cardKey && varKey) this.setRowOpen(cardKey, varKey, true);
                } catch (e) {}

                // Ensure container can fit the newly opened content
                this.adjustHeightToFit(row);
            },

            /** Close both message and context inline dumps in the row. */
            closeAllInlinesInRow(row) {
                const inlines = row.querySelectorAll('.saci-preview .saci-dump-inline');
                inlines.forEach(inline => {
                    const cell = inline.closest('.saci-preview');
                    const preview = cell ? cell.querySelector('.saci-inline-preview') : null;
                    inline.classList.add('saci-hidden');
                    inline.style.display = 'none';
                    if (preview) preview.style.display = 'inline';
                });
                try {
                    const cardKey = this.getCardKey(row.closest('.saci-card'));
                    const varKey = this.getVarKey(row);
                    if (cardKey && varKey) this.setRowOpen(cardKey, varKey, false);
                } catch (e) {}
            },

            /** Collapse when clicking the inline container (not inside sf-dump).
             *  Collapses both message and context inlines within the same row. */
            collapseInlineFromContainer(inline) {
                try {
                    const row = inline.closest('tr[data-saci-var-key]');
                    if (!row) return;
                    // Collapse all inline dumps in the same row (message + context)
                    row.querySelectorAll('.saci-preview').forEach(cell => {
                        const cellInline = cell.querySelector('.saci-dump-inline');
                        const cellPreview = cell.querySelector('.saci-inline-preview');
                        if (cellInline) {
                            cellInline.classList.add('saci-hidden');
                            cellInline.style.display = 'none';
                        }
                        if (cellPreview) cellPreview.style.display = 'inline';
                    });
                    try {
                        const cardKey = this.getCardKey(row.closest('.saci-card'));
                        const varKey = this.getVarKey(row);
                        if (cardKey && varKey) this.setRowOpen(cardKey, varKey, false);
                    } catch (e) {}
                } catch (e) {}
            },

            /** Handles click on a variable row with lazy dump loading. @param {HTMLTableRowElement} row @param {MouseEvent=} evt */
            async onVarRowClick(row, evt) {
                if (evt && this.shouldIgnoreRowClick(evt)) {
                    return;
                }
                const inline = row.querySelector('.saci-dump-inline');
                if (inline) {
                    const anyContentEmpty = Array.from(row.querySelectorAll('.saci-preview .saci-dump-inline')).some(el => {
                        const c = el.querySelector('.saci-dump-content');
                        return c && c.childElementCount === 0;
                    });
                    const anyVisible = Array.from(row.querySelectorAll('.saci-preview .saci-dump-inline')).some(el => el.style.display !== 'none');
                    if (!anyVisible) {
                        // Open both; load empties
                        this.openAllInlinesInRow(row);
                        this.adjustHeightToFit(row);
                        return;
                    }
                    // Toggle both
                    this.closeAllInlinesInRow(row);
                    return;
                }

                const valueRow = row.nextElementSibling; if (!valueRow) return;
                const container = valueRow.querySelector('.saci-dump');
                const hasDump = !!(container && container.getAttribute('data-dump-id'));
                if (hasDump) {
                    const dumpId = container.getAttribute('data-dump-id');
                    const requestId = container.getAttribute('data-request-id');
                    const content = container.querySelector('.saci-dump-content');
                    const isEmpty = content && content.childElementCount === 0 && !content.textContent.trim();
                    if (isEmpty) {
                        // Open row immediately and show preview/loader, then load async for better perceived performance
                        this.toggleVarRow(row);
                        const loading = container.querySelector('.saci-dump-loading');
                        if (loading) {
                            loading.classList.remove('saci-hidden');
                            loading.style.display = 'block';
                        }
                        // Show preview right away if available
                        const previewSpan = row.querySelector('.saci-inline-preview');
                        if (previewSpan && previewSpan.textContent) {
                            content.textContent = previewSpan.textContent;
                        }
                        // fire and forget
                        this.loadDumpFromIds(container, requestId, dumpId).catch(() => {});
                        this.adjustHeightToFit(row);
                        return;
                    }
                }
                this.toggleVarRow(row);
                this.adjustHeightToFit(row);
            },

            /** Helper: show inline dump area and hide preview. */
            showInlineDump(row, inline) {
                const previewSpan = row.querySelector('.saci-inline-preview');
                if (previewSpan) previewSpan.style.display = 'none';
                inline.classList.remove('saci-hidden');
                inline.style.display = 'block';
            },

            /** Helper: toggle both inline dumps in the row. */
            toggleInlineDump(row, _inlineIgnored) {
                const anyVisible = Array.from(row.querySelectorAll('.saci-preview .saci-dump-inline')).some(el => el.style.display !== 'none');
                if (anyVisible) this.closeAllInlinesInRow(row); else this.openAllInlinesInRow(row);
            },

            /** Ensure the bar height is sufficient to display newly expanded content and keep the row visible. */
            adjustHeightToFit(row) {
                try {
                    const rootEl = row && row.closest ? row.closest('#saci') : null;
                    if (!rootEl || rootEl.classList.contains('saci-collapsed')) return;
                    const contentEl = rootEl.querySelector('#saci-content');
                    if (!contentEl) return;
                    const headerH = 36;
                    const MAX = Math.round((window.innerHeight || document.documentElement.clientHeight) * 0.9);
                    const desired = Math.min(MAX, (contentEl.scrollHeight || 0) + headerH);
                    const current = rootEl.getBoundingClientRect().height;
                    if (desired > current + 2) {
                        rootEl.style.maxHeight = desired + 'px';
                        contentEl.style.maxHeight = 'calc(' + desired + 'px - ' + headerH + 'px)';
                        try { storage.set('saci.maxHeightPx', String(desired)); } catch (e) {}
                    }
                    // Nudge scroll to ensure row is fully visible
                    const r = row.getBoundingClientRect();
                    const c = contentEl.getBoundingClientRect();
                    if (r.bottom > c.bottom) {
                        contentEl.scrollTop += (r.bottom - c.bottom);
                    } else if (r.top < c.top) {
                        contentEl.scrollTop -= (c.top - r.top);
                    }
                } catch (e) {}
            },

            /** Helper: open inline and load content, persisting state. */
            async openInlineAndLoad(row, inline, requestId, dumpId, loading, content) {
                try {
                    // Open immediately to show loader and avoid perceived lag
                    this.showInlineDump(row, inline);
                    if (loading) {
                        loading.classList.remove('saci-hidden');
                        loading.style.display = 'block';
                    }
                    // Show preview right away if available and content is empty
                    const previewSpan = row.querySelector('.saci-inline-preview');
                    const isEmpty = content && content.childElementCount === 0 && !content.textContent.trim();
                    if (previewSpan && previewSpan.textContent && isEmpty) {
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
                    if (loading) {
                        loading.classList.add('saci-hidden');
                        loading.style.display = 'none';
                    }
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
                finally {
                    if (loading) {
                        loading.classList.add('saci-hidden');
                        loading.style.display = 'none';
                    }
                }
            },

            /** Loads dump HTML into a container without toggling rows. */
            async loadDumpInto(container, requestId, dumpId) {
                const loading = container.querySelector('.saci-dump-loading');
                const content = container.querySelector('.saci-dump-content');
                try {
                    if (loading) {
                        loading.classList.remove('saci-hidden');
                        loading.style.display = 'block';
                    }
                    const html = await dumps.fetchHtml(requestId, dumpId);
                    if (content) content.innerHTML = html;
                } catch (e) {
                    if (content) content.textContent = 'Failed to load dump';
                } finally {
                    if (loading) {
                        loading.classList.add('saci-hidden');
                        loading.style.display = 'none';
                    }
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

    // Vanilla-only setup that wires the bar behavior without Alpine
    const setupVanilla = () => {
        try {
            const root = document.getElementById('saci');
            if (!root) return;
            const api = window.saciBar();
            // Shared click-suppression flag to avoid collapsing after drag
            let suppressClickOnce = false;
            // Ensure content starts hidden until expanded by code
            // Alpine.js no longer used

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

                // Initialize log filters when logs tab is opened
                if (name === 'logs') {
                    // Use setTimeout to ensure DOM is ready
                    setTimeout(() => logFilters.init(), 0);
                }
                // Ensure selected panel is un-cloaked
                const current = name === 'views'
                  ? root.querySelector('#saci-tabpanel-views')
                  : name === 'resources'
                    ? root.querySelector('#saci-tabpanel-request')
                    : name === 'route'
                      ? root.querySelector('#saci-tabpanel-route')
                      : root.querySelector('#saci-tabpanel-logs');
                // Alpine.js no longer used
            };
            const togglePanels = () => {
                const panels = {
                    views: root.querySelector('#saci-tabpanel-views'),
                    resources: root.querySelector('#saci-tabpanel-request'),
                    route: root.querySelector('#saci-tabpanel-route'),
                    logs: root.querySelector('#saci-tabpanel-logs')
                };

                // Check if user prefers reduced motion
                const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                Object.keys(panels).forEach(k => {
                    const el = panels[k]; if (!el) return;
                    const isActive = (k === state.tab);

                    if (prefersReducedMotion) {
                        // No transition for users who prefer reduced motion
                        el.style.display = isActive ? 'block' : 'none';
                        el.classList.toggle('saci-panel-active', isActive);
                    } else if (isActive) {
                        // === ENTERING PANEL (new active tab) ===

                        // 1. Make visible and add enter state
                        el.style.display = 'block';
                        el.classList.remove('saci-panel-active', 'saci-panel-exit');
                        el.classList.add('saci-panel-enter');

                        // 2. Force reflow
                        void el.offsetHeight;

                        // 3. Transition to active state
                        requestAnimationFrame(() => {
                            el.classList.remove('saci-panel-enter');
                            el.classList.add('saci-panel-active');
                        });
                    } else {
                        // === EXITING PANEL (was active, now hiding) ===

                        // Skip if already hidden
                        if (el.style.display === 'none') return;

                        // 1. Add exit state
                        el.classList.remove('saci-panel-active', 'saci-panel-enter');
                        el.classList.add('saci-panel-exit');

                        // 2. Wait for exit animation to complete, then hide
                        setTimeout(() => {
                            if (el.classList.contains('saci-panel-exit')) {
                                el.style.display = 'none';
                                el.classList.remove('saci-panel-exit');
                            }
                        }, 320); // Match longest CSS transition (transform: 320ms)
                    }
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
                    // Alpine.js no longer used
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
                    row.addEventListener('click', (ev) => { ev.stopPropagation(); api.onVarRowClick(row, ev); });
                    row.setAttribute('tabindex', '0');
                    row.addEventListener('keydown', (ev) => {
                        if (ev.key === 'Enter' || ev.key === ' ') {
                            if (api.shouldIgnoreRowClick(ev)) return;
                            ev.preventDefault();
                            api.onVarRowClick(row, ev);
                        }
                    });
                    // Bind specific preview spans to open their corresponding inline dumps
                    row.querySelectorAll('.saci-inline-preview').forEach(preview => {
                        if (preview.__saci_bound) return;
                        preview.__saci_bound = true;
                        preview.addEventListener('click', (e) => { e.stopPropagation(); api.openInlineFromPreview(preview); });
                        preview.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); api.openInlineFromPreview(preview); } });
                        preview.setAttribute('tabindex', '0');
                        preview.setAttribute('role', 'button');
                        preview.setAttribute('data-saci-stop-row-toggle', '1');
                    });
                    // Bind inline container to collapse when clicking outside the dump content
                    row.querySelectorAll('.saci-dump-inline').forEach(inline => {
                        if (inline.__saci_bound) return;
                        inline.__saci_bound = true;
                        inline.setAttribute('data-saci-stop-row-toggle', '1');
                        inline.addEventListener('click', (e) => {
                            const inDump = e.target && e.target.closest ? e.target.closest('.sf-dump') : null;
                            if (inDump) return; // allow interacting with dump controls
                            e.stopPropagation();
                            api.collapseInlineFromContainer(inline);
                        });
                        inline.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                                const inDump = e.target && e.target.closest ? e.target.closest('.sf-dump') : null;
                                if (inDump) return;
                                e.preventDefault(); e.stopPropagation();
                                api.collapseInlineFromContainer(inline);
                            }
                        });
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
                            // Check for inline dumps (logs have both message and context)
                            const inlines = row.querySelectorAll('.saci-dump-inline');
                            if (inlines.length > 0) {
                                // Restore ALL inline dumps in the row (message + context)
                                inlines.forEach(inline => {
                                    const cell = inline.closest('.saci-preview');
                                    const preview = cell ? cell.querySelector('.saci-inline-preview') : null;
                                    if (preview && preview.style) preview.style.display = 'none';
                                    inline.classList.remove('saci-hidden');
                                    inline.style.display = 'block';

                                    const content = inline.querySelector('.saci-dump-content');
                                    const dumpId = inline.getAttribute('data-dump-id');
                                    const requestId = inline.getAttribute('data-request-id');
                                    const isEmpty = content && content.childElementCount === 0 && !content.textContent.trim();

                                    // Fallback: immediately render preview text so area is never empty
                                    if (isEmpty && preview && preview.textContent) {
                                        content.textContent = preview.textContent;
                                    }

                                    // Load full dump if available
                                    if (dumpId && requestId && isEmpty) {
                                        api.loadDumpInto(inline, requestId, dumpId);
                                    }
                                });
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
                                const preview = row.querySelector('.saci-inline-preview');
                                const isEmpty = content && content.childElementCount === 0 && !content.textContent.trim();
                                // Fallback: preview text immediately while loading
                                if (isEmpty && preview && preview.textContent) {
                                    content.textContent = preview.textContent;
                                }
                                if (dumpId && requestId && isEmpty) {
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
            // Ensure initial panel has active class (no animation on first load)
            if (initialPanel) {
                initialPanel.classList.remove('saci-panel-enter', 'saci-panel-exit');
                initialPanel.classList.add('saci-panel-active');
            }
            // Alpine.js no longer used

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
            const logsCount = (root.getAttribute('data-logs-count') || '0');
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
                    } else if (state.tab === 'logs') {
                        const logsLabel = (parseInt(logsCount) === 1) ? 'log' : 'logs';
                        right.innerHTML = '<div class="saci-summary" style="margin:0;"><div class="saci-summary-right">' +
                          '<strong>' + logsCount + '</strong> ' + logsLabel + ' collected</div></div>';
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
                    } else if (state.tab === 'logs') {
                        const logsLabel = (parseInt(logsCount) === 1) ? 'log' : 'logs';
                        version.innerHTML = '<strong>' + logsCount + '</strong> ' + logsLabel;
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
                        root.classList.remove('saci-no-transition');
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
                            root.classList.add('saci-no-transition');
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

            // Set callback for virtual scroll to reattach listeners after render
            virtualScroll.setOnRender(() => {
                attachVarRowListeners();
            });

            // Initialize log filters if logs tab is initially selected
            if (state.tab === 'logs') {
                setTimeout(() => logFilters.init(), 0);
            }

            // Vanilla tooltips
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
            // Safety: delegated listeners to ensure row toggling works even if per-row bindings fail
            root.addEventListener('click', (e) => {
                const pv = e.target && e.target.closest ? e.target.closest('.saci-inline-preview') : null;
                if (pv && root.contains(pv)) { e.stopPropagation(); api.openInlineFromPreview(pv); return; }
                const tr = e.target && e.target.closest ? e.target.closest('tr[data-saci-var-key]') : null;
                if (tr && root.contains(tr)) {
                    if (api.shouldIgnoreRowClick(e)) return;
                    e.stopPropagation();
                    api.onVarRowClick(tr, e);
                }
            });
        } catch (e) {}
    };

    // Always run vanilla setup
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupVanilla);
    } else {
        setupVanilla();
    }
})();


