@php
    /** @var array<string, array{label: string, code: string, path: string, x: float, y: float}> $zones */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Location> $locations */
    $byZone = $locations->keyBy('map_zone');
@endphp
<figure class="pg-plant-map" aria-label="Northridge plant floor plan">
    <svg class="pg-plant-map-svg" viewBox="0 0 1000 640" role="img" aria-labelledby="plant-map-title">
        <title id="plant-map-title">Northridge plant schematic</title>
        <rect class="pg-plant-map-canvas" x="0" y="0" width="1000" height="640" rx="18"></rect>
        <text class="pg-plant-map-brand" x="40" y="28">Northridge Processing · Plant plan</text>
        <path class="pg-plant-map-corridor" d="M260 95 H700"></path>
        <path class="pg-plant-map-corridor" d="M145 310 V340"></path>
        <path class="pg-plant-map-corridor" d="M370 310 V340"></path>
        <path class="pg-plant-map-corridor" d="M830 160 V180"></path>
        <path class="pg-plant-map-corridor" d="M180 520 V540"></path>

        @foreach ($zones as $zoneKey => $zone)
            @php
                $place = $byZone->get($zoneKey);
                $isActive = ($activeZone ?? null) === $zoneKey;
                $href = $place ? route('locations.show', $place) : null;
            @endphp
            <a
                @if ($href) href="{{ $href }}" @endif
                class="pg-plant-zone {{ $isActive ? 'is-active' : '' }} {{ $href ? '' : 'is-disabled' }}"
                @if ($place) aria-label="{{ $place->name }}" @endif
            >
                <path d="{{ $zone['path'] }}"></path>
                <text x="{{ $zone['x'] }}" y="{{ $zone['y'] - 8 }}" class="pg-plant-zone-label">{{ $zone['label'] }}</text>
                <text x="{{ $zone['x'] }}" y="{{ $zone['y'] + 12 }}" class="pg-plant-zone-code">{{ $zone['code'] }}</text>
            </a>
        @endforeach

        @foreach ($pins ?? [] as $pin)
            <a href="{{ $pin['url'] }}" class="pg-plant-pin is-{{ $pin['kind'] }}" aria-label="{{ $pin['label'] }}">
                <circle cx="{{ $pin['x'] }}" cy="{{ $pin['y'] }}" r="7"></circle>
                <text x="{{ $pin['x'] + 10 }}" y="{{ $pin['y'] + 4 }}">{{ $pin['label'] }}</text>
            </a>
        @endforeach
    </svg>
    <figcaption class="pg-plant-map-caption">
        Click a zone to open that place. Pins mark assets and network devices at the selected location.
    </figcaption>
</figure>
