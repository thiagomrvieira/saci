{{-- Log Filters - Aligned with table columns --}}
<div class="saci-log-filters" id="saci-log-filters">
    <div class="saci-filter-grid">
        {{-- Level Filter (aligned with Level column) --}}
        <div class="saci-filter-cell saci-filter-level">
            <select
                id="saci-log-level-filter"
                class="saci-filter-input"
                aria-label="Filter by level"
                title="Filter by level"
            >
                <option value="">All</option>
                <option value="emergency">EMERGENCY</option>
                <option value="alert">ALERT</option>
                <option value="critical">CRITICAL</option>
                <option value="error">ERROR</option>
                <option value="warning">WARNING</option>
                <option value="notice">NOTICE</option>
                <option value="info">INFO</option>
                <option value="debug">DEBUG</option>
            </select>
        </div>

        {{-- Time Filter (aligned with Time column) --}}
        <div class="saci-filter-cell saci-filter-time">
            <input
                type="text"
                id="saci-log-time-filter"
                class="saci-filter-input"
                placeholder="HH:MM:SS or 14:3*"
                aria-label="Filter by time"
                title="Examples: 14:30:15, 14:3*, *:45, 14:*"
                autocomplete="off"
                spellcheck="false"
            >
        </div>

        {{-- Message/Context Search (spans both columns) --}}
        <div class="saci-filter-cell saci-filter-search">
            <div class="saci-search-wrapper">
                <input
                    type="text"
                    id="saci-log-search"
                    class="saci-filter-input saci-search-input"
                    placeholder="🔍 Search in message and context..."
                    aria-label="Search logs"
                    autocomplete="off"
                >
                <button
                    id="saci-log-search-clear"
                    class="saci-search-clear saci-hidden"
                    aria-label="Clear search"
                    title="Clear search"
                >×</button>
            </div>
        </div>
    </div>

    {{-- Secondary controls row --}}
    <div class="saci-filter-actions">
        <div class="saci-filter-toggles">
            <label class="saci-errors-toggle">
                <input
                    type="checkbox"
                    id="saci-log-errors-only"
                    aria-label="Show errors only"
                >
                <span class="saci-toggle-label">⚠️ Errors Only</span>
            </label>

            <label class="saci-regex-toggle" title="Enable regex search">
                <input
                    type="checkbox"
                    id="saci-log-regex"
                    aria-label="Enable regex"
                >
                <span class="saci-toggle-label">.*</span>
            </label>
        </div>

        <div class="saci-filter-stats" id="saci-log-stats">
            <span id="saci-log-stats-text"></span>
        </div>
    </div>
</div>

<table class="saci-table saci-table-logs" role="table" aria-label="Application logs">
    <thead>
        <tr>
            <th class="saci-col-level" scope="col">Level</th>
            <th class="saci-col-time" scope="col">Time</th>
            <th class="saci-col-message" scope="col">Message</th>
            <th class="saci-col-context" scope="col">Context</th>
        </tr>
    </thead>
    <tbody>
    @forelse($logs as $i => $log)
        @php
            $level = strtolower($log['level'] ?? 'info');
            $rowKey = 'log-' . $i;
        @endphp
        <tr data-saci-var-key="{{ $rowKey }}">
            <td class="saci-var-name saci-col-level">
                <span class="saci-badge saci-badge-level" aria-label="Log level: {{ $level }}">
                    {{ strtoupper($level) }}
                </span>
            </td>
            <td class="saci-var-type saci-col-time">{{ $log['time'] ?? '' }}</td>
            <td class="saci-preview saci-col-message">
                <span class="saci-inline-preview">{{ $log['message_preview'] ?? '' }}</span>
                <div class="saci-dump-inline saci-hidden" data-request-id="{{ $requestId ?? '' }}" data-dump-id="{{ $log['message_dump_id'] ?? '' }}">
                    <div class="saci-dump-loading saci-hidden">Loading…</div>
                    <div class="saci-dump-content">{!! $log['message_inline_html'] ?? '' !!}</div>
                </div>
            </td>
            <td class="saci-preview saci-col-context">
                <span class="saci-inline-preview">{{ $log['context_preview'] ?? '' }}</span>
                <div class="saci-dump-inline saci-hidden" data-request-id="{{ $requestId ?? '' }}" data-dump-id="{{ $log['context_dump_id'] ?? '' }}">
                    <div class="saci-dump-loading saci-hidden">Loading…</div>
                    <div class="saci-dump-content">{!! $log['context_inline_html'] ?? '' !!}</div>
                </div>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="4" class="saci-empty-state">No logs collected for this request</td>
        </tr>
    @endforelse
    </tbody>
</table>


