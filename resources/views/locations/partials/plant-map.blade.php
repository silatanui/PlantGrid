@php
    /** @var array<string, array<string, mixed>> $zones */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Location> $locations */
    $byZone = $locations->keyBy('map_zone');
    $pinPayload = collect($pins ?? [])->values();
@endphp
<section class="pg-plant-stage" data-plant-map>
    <div class="pg-plant-toolbar" role="group" aria-label="Map controls">
        <button type="button" class="btn btn-sm btn-pg-line" data-plant-zoom-in title="Zoom in">+</button>
        <button type="button" class="btn btn-sm btn-pg-line" data-plant-zoom-out title="Zoom out">−</button>
        <button type="button" class="btn btn-sm btn-pg-line" data-plant-zoom-reset title="Reset view">Reset</button>
        <span class="pg-plant-toolbar-hint">Scroll to zoom · drag to pan · click a pin for details</span>
    </div>

    <div class="pg-plant-viewport" data-plant-viewport>
        <div class="pg-plant-world" data-plant-world>
            <svg class="pg-plant-map-svg" viewBox="0 0 1200 800" role="img" aria-labelledby="plant-map-title">
                <title id="plant-map-title">Northridge Processing campus plan</title>
                <defs>
                    <pattern id="pg-grass" width="24" height="24" patternUnits="userSpaceOnUse">
                        <rect width="24" height="24" fill="#d7ead8"/>
                        <path d="M4 18c2-6 4-10 4-14M12 20c2-7 3-11 5-16M20 18c1-5 3-9 3-13" stroke="#b7d4b9" stroke-width="1.2" fill="none"/>
                    </pattern>
                    <pattern id="pg-paving" width="16" height="16" patternUnits="userSpaceOnUse">
                        <rect width="16" height="16" fill="#d9dde3"/>
                        <path d="M0 16H16M16 0V16" stroke="#c5cad2" stroke-width="1"/>
                    </pattern>
                    <filter id="pg-soft" x="-10%" y="-10%" width="120%" height="120%">
                        <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#1f2a33" flood-opacity="0.12"/>
                    </filter>
                </defs>

                {{-- Ground --}}
                <rect class="pg-plant-ground" x="0" y="0" width="1200" height="800" rx="20"/>
                <rect fill="url(#pg-grass)" x="20" y="20" width="160" height="210" rx="16"/>
                <rect fill="url(#pg-grass)" x="1120" y="230" width="60" height="250" rx="12"/>
                <rect fill="url(#pg-grass)" x="380" y="220" width="360" height="200" rx="18"/>
                <ellipse fill="url(#pg-grass)" cx="200" cy="120" rx="70" ry="45"/>
                <ellipse fill="url(#pg-grass)" cx="1050" cy="740" rx="90" ry="40"/>

                {{-- Trees --}}
                <g class="pg-plant-trees" aria-hidden="true">
                    <circle cx="70" cy="70" r="18" fill="#6fa87a"/><rect x="66" y="80" width="8" height="16" fill="#6b4f2e"/>
                    <circle cx="130" cy="55" r="14" fill="#5f9a6c"/><rect x="127" y="64" width="6" height="14" fill="#6b4f2e"/>
                    <circle cx="90" cy="150" r="16" fill="#7bb384"/><rect x="87" y="158" width="6" height="14" fill="#6b4f2e"/>
                    <circle cx="560" cy="300" r="20" fill="#6fa87a"/><rect x="556" y="312" width="8" height="16" fill="#6b4f2e"/>
                    <circle cx="500" cy="250" r="14" fill="#5f9a6c"/><rect x="497" y="258" width="6" height="12" fill="#6b4f2e"/>
                    <circle cx="640" cy="270" r="15" fill="#7bb384"/><rect x="637" y="278" width="6" height="12" fill="#6b4f2e"/>
                    <circle cx="1145" cy="720" r="18" fill="#6fa87a"/><rect x="1141" y="730" width="8" height="14" fill="#6b4f2e"/>
                </g>

                {{-- Footpaths / roads --}}
                <g class="pg-plant-paths" aria-hidden="true">
                    <path fill="url(#pg-paving)" d="M510 190 H560 V470 H640 V190 H690 V210 H640 V250 H560 V210 H510 Z"/>
                    <path fill="url(#pg-paving)" d="M300 320 H320 V470 H300 Z"/>
                    <path fill="url(#pg-paving)" d="M520 320 H860 V350 H520 Z"/>
                    <path fill="url(#pg-paving)" d="M200 410 H300 V430 H200 Z"/>
                    <path fill="url(#pg-paving)" d="M200 700 H200 V720 H380 V700 H560 V720 H70 V780 H70 V720 H200 Z"/>
                    <path fill="url(#pg-paving)" d="M470 40 H580 V70 H470 Z"/>
                    <path fill="#b8c0ca" d="M40 760 H400 V790 H40 Z"/>
                    <text class="pg-plant-amenity-label" x="220" y="778">Service driveway</text>
                </g>

                <text class="pg-plant-map-brand" x="40" y="36">Northridge Processing · Campus plan</text>
                <text class="pg-plant-amenity-label" x="100" y="210">Staff garden</text>
                <text class="pg-plant-amenity-label" x="560" y="330">Courtyard</text>

                {{-- Rooms --}}
                @foreach ($zones as $zoneKey => $zone)
                    @php
                        $place = $byZone->get($zoneKey);
                        $isActive = ($activeZone ?? null) === $zoneKey;
                        $href = $place ? route('locations.show', $place) : null;
                    @endphp
                    <g class="pg-plant-zone {{ $isActive ? 'is-active' : '' }}" data-zone="{{ $zoneKey }}">
                        <a @if ($href) href="{{ $href }}" @endif class="pg-plant-zone-hit" @if ($place) aria-label="Open {{ $place->name }}" @endif>
                            <path class="pg-plant-zone-floor" d="{{ $zone['path'] }}" filter="url(#pg-soft)"></path>
                        </a>
                        {{-- Windows --}}
                        @if ($zoneKey === 'adm-bl')
                            <rect class="pg-plant-window" x="800" y="62" width="40" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="860" y="62" width="40" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="920" y="62" width="40" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="980" y="62" width="40" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="1040" y="62" width="40" height="10" rx="2"/>
                        @elseif ($zoneKey === 'ict-of')
                            <rect class="pg-plant-window" x="72" y="280" width="10" height="36" rx="2"/>
                            <rect class="pg-plant-window" x="72" y="330" width="10" height="36" rx="2"/>
                        @elseif ($zoneKey === 'qa-lab')
                            <rect class="pg-plant-window" x="1110" y="280" width="10" height="36" rx="2"/>
                            <rect class="pg-plant-window" x="1110" y="330" width="10" height="36" rx="2"/>
                        @elseif ($zoneKey === 'pkg-hl')
                            <rect class="pg-plant-window" x="120" y="472" width="50" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="220" y="472" width="50" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="320" y="472" width="50" height="10" rx="2"/>
                        @elseif ($zoneKey === 'prc-hl')
                            <rect class="pg-plant-window" x="700" y="472" width="50" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="820" y="472" width="50" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="940" y="472" width="50" height="10" rx="2"/>
                        @elseif ($zoneKey === 'gate')
                            <rect class="pg-plant-window" x="450" y="72" width="36" height="10" rx="2"/>
                            <rect class="pg-plant-window" x="560" y="72" width="36" height="10" rx="2"/>
                        @endif

                        {{-- Doors --}}
                        @if ($zoneKey === 'gate')
                            <path class="pg-plant-door" d="M510 190 A18 18 0 0 1 540 190"/>
                            <line class="pg-plant-door-swing" x1="525" y1="190" x2="525" y2="210"/>
                        @elseif ($zoneKey === 'ict-of')
                            <path class="pg-plant-door" d="M300 320 A16 16 0 0 1 300 350"/>
                        @elseif ($zoneKey === 'srv-rm')
                            <path class="pg-plant-door" d="M320 320 A14 14 0 0 0 320 348"/>
                        @elseif ($zoneKey === 'adm-bl')
                            <path class="pg-plant-door" d="M780 150 A16 16 0 0 0 780 180"/>
                        @elseif ($zoneKey === 'qa-lab')
                            <path class="pg-plant-door" d="M860 320 A16 16 0 0 0 860 350"/>
                        @elseif ($zoneKey === 'pkg-hl')
                            <path class="pg-plant-door" d="M300 470 A18 18 0 0 0 330 470"/>
                            <path class="pg-plant-door" d="M200 700 A16 16 0 0 1 230 700"/>
                        @elseif ($zoneKey === 'prc-hl')
                            <path class="pg-plant-door" d="M640 470 A18 18 0 0 1 670 470"/>
                        @elseif ($zoneKey === 'whs-of')
                            <path class="pg-plant-door" d="M200 720 A12 12 0 0 0 224 720"/>
                        @endif

                        <text class="pg-plant-zone-label" x="{{ $zone['label_x'] }}" y="{{ $zone['label_y'] }}">{{ $zone['label'] }}</text>
                        <text class="pg-plant-zone-code" x="{{ $zone['label_x'] }}" y="{{ $zone['label_y'] + 16 }}">{{ $zone['code'] }}</text>
                    </g>
                @endforeach

                {{-- Reception desk hint at entrance --}}
                <rect class="pg-plant-furniture" x="500" y="120" width="50" height="18" rx="3"/>
                <text class="pg-plant-amenity-label" x="525" y="150">Reception</text>

                {{-- Device / location pins (labels live in the modal, not on the canvas) --}}
                @foreach ($pinPayload as $index => $pin)
                    <g
                        class="pg-plant-pin is-{{ $pin['kind'] }}"
                        data-plant-pin
                        data-pin-index="{{ $index }}"
                        role="button"
                        tabindex="0"
                        aria-label="{{ $pin['title'] ?? $pin['label'] }}"
                        transform="translate({{ $pin['x'] }}, {{ $pin['y'] }})"
                    >
                        <circle r="10"></circle>
                        <circle class="pg-plant-pin-core" r="3.5"></circle>
                        <title>{{ $pin['title'] ?? $pin['label'] }}</title>
                    </g>
                @endforeach
            </svg>
        </div>
    </div>

    <div class="pg-plant-legend">
        <span><i class="pg-plant-swatch is-location"></i> Location</span>
        <span><i class="pg-plant-swatch is-asset"></i> Asset</span>
        <span><i class="pg-plant-swatch is-device"></i> Network</span>
        <span>Paths · doors · gardens included</span>
    </div>

    <dialog class="pg-plant-modal" data-plant-modal>
        <form method="dialog" class="pg-plant-modal-card">
            <header class="pg-plant-modal-head">
                <div>
                    <p class="pg-plant-modal-kind" data-plant-modal-kind></p>
                    <h2 class="pg-plant-modal-title" data-plant-modal-title></h2>
                    <p class="pg-plant-modal-sub" data-plant-modal-sub></p>
                </div>
                <button class="btn btn-sm btn-pg-line" value="cancel" aria-label="Close">Close</button>
            </header>
            <p class="pg-plant-modal-status" data-plant-modal-status></p>
            <p class="pg-plant-modal-body" data-plant-modal-body></p>
            <p class="pg-plant-modal-meta" data-plant-modal-meta></p>
            <footer class="pg-plant-modal-actions">
                <a class="btn btn-pg" data-plant-modal-link href="#">Open record</a>
                <button class="btn btn-pg-line" value="cancel">Keep exploring</button>
            </footer>
        </form>
    </dialog>

    <script type="application/json" data-plant-pins-json>{!! json_encode($pinPayload, JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
</section>
