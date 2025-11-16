@php
    $databaseData = $resources['database'] ?? [];
    $queries = $databaseData['queries'] ?? [];
    $totalQueries = $databaseData['total_queries'] ?? 0;
    $totalTime = $databaseData['total_time'] ?? 0;
    $slowQueries = $databaseData['slow_queries'] ?? [];
    $duplicates = $databaseData['duplicate_queries'] ?? [];
    $nPlusOne = $databaseData['possible_n_plus_one'] ?? [];

    // Helper function to format time with unit
    $formatTime = function($ms) {
        if ($ms >= 1000) {
            return round($ms / 1000, 2) . 's';
        }
        return round($ms, 2) . 'ms';
    };
@endphp

{{-- Alert for N+1 Queries --}}
@if(count($nPlusOne) > 0)
<div class="saci-alert saci-alert-danger">
    <strong>⚠️ N+1 Queries Detected!</strong>
    <div class="saci-n1-summary">
        @foreach($nPlusOne as $pattern)
            <div class="saci-n1-item">
                <code class="saci-n1-pattern">{{ Str::limit($pattern['pattern'], 120) }}</code>
                <span class="saci-n1-count">executed <strong>{{ $pattern['count'] }}×</strong> ({{ $formatTime($pattern['total_time']) }} total)</span>
                <button class="saci-btn-expand-n1" data-pattern-id="{{ $loop->index }}">Show examples</button>
                <div class="saci-n1-examples saci-hidden" data-pattern-id="{{ $loop->index }}">
                    @foreach($pattern['examples'] as $example)
                        <div class="saci-n1-example">
                            <code>{{ $example['sql'] }}</code>
                            @if(!empty($example['bindings']))
                                <div class="saci-bindings">
                                    Bindings: {{ json_encode($example['bindings']) }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Duplicate Queries Warning --}}
@if(count($duplicates) > 0 && count($nPlusOne) === 0)
<div class="saci-alert saci-alert-warning saci-alert-compact">
    <strong>ℹ️ Duplicate Queries Found</strong>
    <span>{{ count($duplicates) }} queries were executed multiple times. Consider caching or eager loading.</span>
</div>
@endif

{{-- Query Filters --}}
<div class="saci-db-filters" id="saci-db-filters">
    <div class="saci-filter-row">
        <div class="saci-search-wrapper">
            <input
                type="text"
                id="saci-db-search"
                class="saci-filter-input saci-search-input"
                placeholder="🔍 Search queries..."
                aria-label="Search queries"
                autocomplete="off"
            >
            <button
                id="saci-db-search-clear"
                class="saci-search-clear saci-hidden"
                aria-label="Clear search"
                title="Clear search"
            >×</button>
        </div>
    </div>

    <div class="saci-filter-actions">
        <div class="saci-filter-toggles">
            @if(count($slowQueries) > 0)
            <label class="saci-filter-toggle">
                <input
                    type="checkbox"
                    id="saci-db-slow-only"
                    aria-label="Show slow queries only"
                >
                <span class="saci-toggle-label">Slow Only (&gt;100ms)</span>
            </label>
            @endif

            <label class="saci-filter-toggle">
                <input
                    type="checkbox"
                    id="saci-db-select-only"
                    aria-label="Show SELECT queries only"
                >
                <span class="saci-toggle-label">SELECT Only</span>
            </label>
        </div>

        <div class="saci-filter-stats" id="saci-db-stats">
            <span id="saci-db-stats-text">Showing {{ $totalQueries }} queries</span>
        </div>
    </div>
</div>

{{-- Queries Table --}}
<table class="saci-table saci-table-database" role="table" aria-label="Database queries">
    <thead>
        <tr>
            <th class="saci-col-db-index" scope="col">#</th>
            <th class="saci-col-db-time" scope="col">Time</th>
            <th class="saci-col-db-query" scope="col">Query</th>
            <th class="saci-col-db-caller" scope="col">Called From</th>
        </tr>
    </thead>
    <tbody>
    @forelse($queries as $i => $query)
        @php
            $rowKey = 'db-' . $i;
            $isSlow = $query['is_slow'] ?? false;
            $sql = $query['sql'] ?? '';
            $bindings = $query['bindings'] ?? [];
            $time = $query['time'] ?? 0;
            $caller = $query['caller'] ?? null;
            $database = $query['database'] ?? 'default';
            $driver = $query['driver'] ?? 'Unknown';

            // Determine query type for filtering
            $queryType = 'other';
            if (stripos($sql, 'SELECT') === 0) {
                $queryType = 'select';
            } elseif (stripos($sql, 'INSERT') === 0) {
                $queryType = 'insert';
            } elseif (stripos($sql, 'UPDATE') === 0) {
                $queryType = 'update';
            } elseif (stripos($sql, 'DELETE') === 0) {
                $queryType = 'delete';
            }
        @endphp
        <tr
            data-saci-db-key="{{ $rowKey }}"
            data-query-type="{{ $queryType }}"
            @if($isSlow) data-is-slow="true" @endif
            class="@if($isSlow) saci-db-row-slow @endif"
        >
            <td class="saci-col-db-index">{{ $i + 1 }}</td>
            <td class="saci-col-db-time">
                <span class="saci-db-time @if($isSlow) saci-db-time-slow @endif">
                    {{ $formatTime($time) }}
                </span>
            </td>
            <td class="saci-col-db-query">
                <div class="saci-query-wrapper">
                    <code class="saci-query-sql">{{ $sql }}</code>
                    @if(!empty($bindings))
                        <button class="saci-btn-toggle-bindings" data-query-id="{{ $i }}">
                            <span class="saci-bindings-closed">Show bindings</span>
                            <span class="saci-bindings-open saci-hidden">Hide bindings</span>
                        </button>
                        <div class="saci-query-bindings saci-hidden" data-query-id="{{ $i }}">
                            <strong>Bindings:</strong>
                            <pre>{{ json_encode($bindings, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            </td>
            <td class="saci-col-db-caller">
                @if($caller)
                    <div class="saci-caller-info">
                        <div class="saci-caller-file" title="{{ $caller['file'] ?? '' }}">
                            {{ basename($caller['file'] ?? '') }}:{{ $caller['line'] ?? '' }}
                        </div>
                        @if(isset($caller['class']))
                            <div class="saci-caller-method">
                                {{ $caller['class'] }}::{{ $caller['function'] ?? '' }}()
                            </div>
                        @endif
                        <div class="saci-caller-connection">
                            <span class="saci-db-driver">{{ $driver }}</span> • <span class="saci-db-connection">{{ $database }}</span>
                        </div>
                    </div>
                @else
                    <span class="saci-text-muted">—</span>
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="4" class="saci-empty-state">No database queries executed</td>
        </tr>
    @endforelse
    </tbody>
</table>

@if(count($duplicates) > 0)
<div class="saci-duplicates-section">
    <h4 class="saci-section-title">Duplicate Queries</h4>
    <table class="saci-table saci-table-duplicates">
        <thead>
            <tr>
                <th scope="col">Query</th>
                <th scope="col">Count</th>
                <th scope="col">Total Time</th>
            </tr>
        </thead>
        <tbody>
            @foreach($duplicates as $dup)
                <tr>
                    <td><code class="saci-query-sql">{{ Str::limit($dup['sql'], 100) }}</code></td>
                    <td><strong>{{ $dup['count'] }}×</strong></td>
                    <td>{{ $formatTime($dup['total_time']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

