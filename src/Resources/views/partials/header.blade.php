<div
    id="saci-header"
    tabindex="0"
    role="button"
    aria-expanded="false"
>
    <div class="saci-title">
        <span>Saci</span>
        <div class="saci-tabs" role="tablist">
            <button
                class="saci-tab"
                role="tab"
                aria-selected="false"
                aria-controls="saci-tabpanel-views"
                id="saci-tab-views"
                type="button"
            >
                Views
            </button>
            <button
                class="saci-tab"
                role="tab"
                aria-selected="false"
                aria-controls="saci-tabpanel-request"
                id="saci-tab-request"
                type="button"
            >
                Request
            </button>
            <button
                class="saci-tab"
                role="tab"
                aria-selected="false"
                aria-controls="saci-tabpanel-route"
                id="saci-tab-route"
                type="button"
            >
                Route
            </button>
        </div>
    </div>
    <div id="saci-controls" class="saci-controls">
        <div
            id="saci-controls-buttons"
        >
            <template>
                <div class="saci-summary" style="margin:0;">
                    @if(!empty($viewsMeta))
                        <div class="saci-summary-right">{{ $total }} views loaded in <strong
                            class="{{ $viewsMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $viewsMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                        >{{ $viewsMeta['display'] ?? '' }}</strong></div>
                    @endif
                </div>
            </template>
            <template>
                <div class="saci-summary" style="margin:0;">
                    @if(!empty($requestMeta))
                        <div class="saci-summary-right">Response time: <strong
                            class="{{ $requestMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $requestMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                        >{{ $requestMeta['display'] ?? '' }}</strong></div>
                    @endif
                </div>
            </template>
            <template>
                <div class="saci-summary" style="margin:0;">
                    <div class="saci-summary-left">{{ $method }} {{ $uri }}</div>
                </div>
            </template>
        </div>
        <span
            id="saci-controls-version"
            class="saci-subtle"
        >
            <template>
                <span>
                    @if(!empty($viewsMeta))
                        {{ $total }} views loaded in
                        <strong
                            class="{{ $viewsMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $viewsMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                        >{{ $viewsMeta['display'] ?? '' }}</strong>
                    @endif
                </span>
            </template>
            <template>
                <span>
                    @if(!empty($requestMeta))
                        Response time:
                        <strong
                            class="{{ $requestMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $requestMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                        >{{ $requestMeta['display'] ?? '' }}</strong>
                    @endif
                </span>
            </template>
            <template>
                <span>
                    {{ $method }} {{ $uri }}
                </span>
            </template>
        </span>
        <div
            id="saci-arrow"
            class="saci-subtle"
            >
            ▶
        ></div>
    </div>
</div>

