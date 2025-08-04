<div id="saci" style="position: fixed; {{ config('saci.ui.position', 'bottom') }}: 0; left: 0; right: 0; background: #1a202c; color: #e2e8f0; padding: 0; z-index: 9999; font-family: 'Fira Code', monospace; max-height: {{ config('saci.ui.max_height', '30vh') }}; overflow: hidden; box-shadow: 0 -2px 10px rgba(0,0,0,0.2);">
    <div style="display: flex; justify-content: space-between; background: #2d3748; padding: 8px 12px; cursor: pointer;" onclick="toggleSaci()">
        <div style="font-weight: bold; color: #63b3ed;">
            <span>Saci</span>
            <span style="margin-left: 10px; color: #a0aec0; font-size: 0.9em;">v{{ $version }} by {{ $author }}</span>
            <span style="margin-left: 10px; color: #68d391;">Views ({{ $total }})</span>
            @php
                $totalDuration = collect($templates)->sum('duration');
            @endphp
            @if($totalDuration > 0)
            <span style="margin-left: 10px; color: #fc8181;">{{ $totalDuration }}ms</span>
            @endif
        </div>
        <div id="saci-arrow" style="color: #a0aec0;">▼</div>
    </div>

    <div id="saci-content" style="padding: 12px; overflow-y: auto; max-height: calc({{ config('saci.ui.max_height', '30vh') }} - 36px);">
        <ul style="margin: 0; padding: 0; list-style: none;">
            @foreach($templates as $template)
            <li style="padding: 8px 0; border-bottom: 1px solid #2d3748;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="color: #68d391; font-family: 'Fira Code', monospace;">{{ $template['path'] }}</span>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span style="color: #f6ad55; font-size: 0.8em; background: #2d3748; padding: 2px 6px; border-radius: 4px;">
                            {{ count($template['data']) }} vars
                        </span>
                        @if(isset($template['duration']))
                        <span style="color: #fc8181; font-size: 0.8em; background: #2d3748; padding: 2px 6px; border-radius: 4px;">
                            {{ $template['duration'] }}ms
                        </span>
                        @endif
                    </div>
                </div>
                @if(!empty($template['data']))
                <div style="margin-top: 4px; font-size: 0.8em; color: #a0aec0;">
                    @foreach($template['data'] as $key => $type)
                    <span style="display: inline-block; margin-right: 8px; margin-bottom: 4px;">
                        <span style="color: #f6e05e;">{{ $key }}</span>:
                        <span style="color: #b794f4;">{{ $type }}</span>
                    </span>
                    @endforeach
                </div>
                @endif
            </li>
            @endforeach
        </ul>
    </div>

    <script>
        function toggleSaci() {
            const content = document.getElementById('saci-content');
            const arrow = document.getElementById('saci-arrow');
            const saci = document.getElementById('saci');

            if (content.style.maxHeight === '0px') {
                content.style.maxHeight = `calc(${saci.style.maxHeight} - 36px)`;
                arrow.textContent = '▼';
            } else {
                content.style.maxHeight = '0px';
                arrow.textContent = '▶';
            }
        }
    </script>
</div>