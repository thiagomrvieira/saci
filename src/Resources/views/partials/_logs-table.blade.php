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


