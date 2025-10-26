<div
    id="saci-header"
    @click.stop="onHeaderClick()"
    @keydown.enter.prevent="toggle()"
    @keydown.space.prevent="toggle()"
    tabindex="0"
    role="button"
    :aria-expanded="!collapsed"
>
    <div class="saci-title">
        <span>Saci</span>
        <div class="saci-tabs" role="tablist">
            <button
                class="saci-tab"
                role="tab"
                :class="{ 'saci-tab--active': (tab==='views') }"
                :aria-selected="(tab==='views')"
                aria-controls="saci-tabpanel-views"
                id="saci-tab-views"
                type="button"
                @click.stop="selectTab('views')"
            >
                Views
            </button>
            <button
                class="saci-tab"
                role="tab"
                :class="{ 'saci-tab--active': (tab==='resources') }"
                :aria-selected="(tab==='resources')"
                aria-controls="saci-tabpanel-request"
                id="saci-tab-request"
                type="button"
                @click.stop="selectTab('resources')"
            >
                Request
            </button>
            <button
                class="saci-tab"
                role="tab"
                :class="{ 'saci-tab--active': (tab==='route') }"
                :aria-selected="(tab==='route')"
                aria-controls="saci-tabpanel-route"
                id="saci-tab-route"
                type="button"
                @click.stop="selectTab('route')"
            >
                Route
            </button>
        </div>
    </div>
    <div id="saci-controls" class="saci-controls">
        <div
            id="saci-controls-buttons"
            x-show="!collapsed"
            x-cloak
        >
            <template x-if="tab==='views'">
                <div class="saci-summary" style="margin:0;">
                    @if(!empty($viewsMeta))
                        <div class="saci-summary-right">{{ $total }} views loaded in <strong
                            class="{{ $viewsMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $viewsMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                            @mouseenter="showTooltip($event)"
                            @mouseleave="hideTooltip()"
                            @focus="showTooltip($event)"
                            @blur="hideTooltip()"
                        >{{ $viewsMeta['display'] ?? '' }}</strong></div>
                    @endif
                </div>
            </template>
            <template x-if="tab==='resources'">
                <div class="saci-summary" style="margin:0;">
                    @if(!empty($requestMeta))
                        <div class="saci-summary-right">Response time: <strong
                            class="{{ $requestMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $requestMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                            @mouseenter="showTooltip($event)"
                            @mouseleave="hideTooltip()"
                            @focus="showTooltip($event)"
                            @blur="hideTooltip()"
                        >{{ $requestMeta['display'] ?? '' }}</strong></div>
                    @endif
                </div>
            </template>
            <template x-if="tab==='route'">
                <div class="saci-summary" style="margin:0;">
                    <div class="saci-summary-left">{{ $method }} {{ $uri }}</div>
                </div>
            </template>
        </div>
        <span
            id="saci-controls-version"
            class="saci-subtle"
            x-show="collapsed"
            x-cloak
        >
            <template x-if="tab==='views'">
                <span>
                    @if(!empty($viewsMeta))
                        {{ $total }} views loaded in
                        <strong
                            class="{{ $viewsMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $viewsMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                            @mouseenter="showTooltip($event)"
                            @mouseleave="hideTooltip()"
                            @focus="showTooltip($event)"
                            @blur="hideTooltip()"
                        >{{ $viewsMeta['display'] ?? '' }}</strong>
                    @endif
                </span>
            </template>
            <template x-if="tab==='resources'">
                <span>
                    @if(!empty($requestMeta))
                        Response time:
                        <strong
                            class="{{ $requestMeta['class'] ?? '' }}"
                            data-saci-tooltip="{{ $requestMeta['tooltip'] ?? '' }}"
                            tabindex="0"
                            @mouseenter="showTooltip($event)"
                            @mouseleave="hideTooltip()"
                            @focus="showTooltip($event)"
                            @blur="hideTooltip()"
                        >{{ $requestMeta['display'] ?? '' }}</strong>
                    @endif
                </span>
            </template>
            <template x-if="tab==='route'">
                <span>
                    {{ $method }} {{ $uri }}
                </span>
            </template>
        </span>
        <div
            id="saci-arrow"
            class="saci-subtle"
            x-text="collapsed ? '▶' : '▼'"
        ></div>
    </div>
</div>

