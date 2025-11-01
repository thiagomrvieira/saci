@php
    /**
     * Logs panel view
     * 
     * @var array<int,array{
     *     level:string,
     *     time:string,
     *     message_preview:string,
     *     message_dump_id:?string,
     *     message_inline_html:?string,
     *     context_preview:string,
     *     context_dump_id:?string,
     *     context_inline_html:?string
     * }> $logs
     * @var string|null $requestId
     */
@endphp

<div class="saci-logs-panel">
    @include('saci::partials.card', [
        'key' => 'logs-table',
        'title' => '',
        'open' => true,
        'content' => view('saci::partials._logs-table', [
            'logs' => $logs ?? [],
            'requestId' => ($requestId ?? null)
        ])->render(),
    ])
</div>



