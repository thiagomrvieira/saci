@php
    /**
     * Database panel view
     *
     * @var array $resources
     * @var string|null $requestId
     */
@endphp

<div class="saci-database-panel">
    @include('saci::partials.card', [
        'key' => 'database-queries',
        'title' => '',
        'open' => true,
        'content' => view('saci::partials._database', [
            'resources' => $resources ?? [],
            'requestId' => ($requestId ?? null)
        ])->render(),
    ])
</div>





