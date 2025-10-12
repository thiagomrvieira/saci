<div
    id="saci-header"
    @click.stop="if(Alpine.store('saci').isResizing || Alpine.store('saci').didResize){ Alpine.store('saci').didResize=false; return; } toggle()"
    @keydown.enter.prevent="toggle()"
    @keydown.space.prevent="toggle()"
    tabindex="0"
    role="button"
    :aria-expanded="!collapsed"
>
    <div class="saci-title">
        <span>Saci</span>
        <div class="saci-sep" style="display:inline-flex; gap: 8px; align-items: center; margin-left: 10px;">
            <button class="saci-btn-ghost" :class="{ 'saci-btn-active': (tab==='views') }" @click.stop="tab='views'; saveTab()">Views ({{ $total }})</button>
            <button class="saci-btn-ghost" :class="{ 'saci-btn-active': (tab==='resources') }" @click.stop="tab='resources'; saveTab()">Resources</button>
        </div>
        @php
            $totalDuration = collect($templates)
                ->filter(fn($t) => isset($t['duration']))
                ->sum('duration');
        @endphp
        @if($totalDuration > 0)
            <span class="saci-duration">{{ $totalDuration }}ms</span>
        @endif
    </div>
    <div id="saci-controls" class="saci-controls">
        <div
            id="saci-controls-buttons"
            x-show="!collapsed"
            x-cloak
        >
            <button
                id="saci-expand"
                aria-label="Expand all"
                class="saci-btn-ghost"
                @click.stop="expandAll()"
            >Expand all</button>
            <button
                id="saci-collapse"
                aria-label="Collapse all"
                class="saci-btn-ghost"
                @click.stop="collapseAll()"
            >Collapse all</button>
        </div>
        <span
            id="saci-controls-version"
            class="saci-subtle"
            x-show="collapsed"
            x-cloak
        >v{{ $version }} by {{ $author }}</span>
        <div
            id="saci-arrow"
            class="saci-subtle"
            x-text="collapsed ? '▶' : '▼'"
        ></div>
    </div>
</div>

