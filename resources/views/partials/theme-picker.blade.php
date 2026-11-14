@php
    $themes = [
        ['id' => 'violet', 'label' => 'Violet', 'swatch' => '#7d259d'],
        ['id' => 'rose', 'label' => 'Rose', 'swatch' => '#b30068'],
        ['id' => 'petrol', 'label' => 'Petrol', 'swatch' => '#0b8f9c'],
        ['id' => 'tide', 'label' => 'Tide', 'swatch' => '#0f5c6b'],
        ['id' => 'lagoon', 'label' => 'Lagoon', 'swatch' => '#148f77'],
        ['id' => 'ocean', 'label' => 'Ocean', 'swatch' => '#1a6b7a'],
        ['id' => 'copper', 'label' => 'Copper', 'swatch' => '#c46a2b'],
        ['id' => 'sand', 'label' => 'Sand', 'swatch' => '#a67c52'],
        ['id' => 'slate', 'label' => 'Slate', 'swatch' => '#3d5a80'],
        ['id' => 'graphite', 'label' => 'Graphite', 'swatch' => '#3a4550'],
        ['id' => 'ember', 'label' => 'Ember', 'swatch' => '#b54708'],
        ['id' => 'plum', 'label' => 'Plum', 'swatch' => '#6b4c7a'],
    ];
    $backgrounds = [
        ['id' => 'mist', 'label' => 'Mist', 'swatch' => '#eef1f4'],
        ['id' => 'white', 'label' => 'White', 'swatch' => '#ffffff'],
        ['id' => 'cloud', 'label' => 'Cloud', 'swatch' => '#e6ebf0'],
        ['id' => 'cool', 'label' => 'Cool', 'swatch' => '#e6eef2'],
        ['id' => 'warm', 'label' => 'Warm', 'swatch' => '#f0ebe4'],
        ['id' => 'lilac', 'label' => 'Lilac', 'swatch' => '#f3eef6'],
        ['id' => 'mint', 'label' => 'Mint', 'swatch' => '#e8f2f0'],
        ['id' => 'blush', 'label' => 'Blush', 'swatch' => '#f6eef2'],
        ['id' => 'sandstone', 'label' => 'Sandstone', 'swatch' => '#f3efe8'],
        ['id' => 'ink', 'label' => 'Ink', 'swatch' => '#1c2430'],
    ];
@endphp
<div class="pg-theme-picker" data-theme-picker>
    <div class="pg-theme-picker-label">Theme colour</div>
    <div class="pg-theme-swatches" role="group" aria-label="Theme colour">
        @foreach ($themes as $theme)
            <button
                class="pg-theme-swatch"
                type="button"
                data-theme-option="{{ $theme['id'] }}"
                title="{{ $theme['label'] }}"
                aria-label="{{ $theme['label'] }} theme"
                aria-pressed="false"
                style="--pg-swatch: {{ $theme['swatch'] }}"
            >
                <span class="visually-hidden">{{ $theme['label'] }}</span>
            </button>
        @endforeach
        <label class="pg-theme-custom" title="Custom colour">
            <button
                class="pg-theme-swatch pg-theme-swatch-custom"
                type="button"
                data-theme-custom-swatch
                aria-label="Custom colour"
                aria-pressed="false"
                style="--pg-swatch: #7d259d"
            >
                <span class="visually-hidden">Custom</span>
                <i data-lucide="pipette" aria-hidden="true"></i>
            </button>
            <input class="pg-theme-color-input" type="color" value="#7d259d" data-theme-custom aria-label="Pick a custom theme colour">
        </label>
    </div>

    <div class="pg-theme-picker-label">Background</div>
    <p class="pg-theme-picker-hint">Page wash only - tables and cards stay light so text stays readable.</p>
    <div class="pg-theme-swatches" role="group" aria-label="Background colour">
        @foreach ($backgrounds as $background)
            <button
                class="pg-theme-swatch pg-theme-swatch-bg"
                type="button"
                data-bg-option="{{ $background['id'] }}"
                title="{{ $background['label'] }}"
                aria-label="{{ $background['label'] }} background"
                aria-pressed="false"
                style="--pg-swatch: {{ $background['swatch'] }}"
            >
                <span class="visually-hidden">{{ $background['label'] }}</span>
            </button>
        @endforeach
        <label class="pg-theme-custom" title="Custom background">
            <button
                class="pg-theme-swatch pg-theme-swatch-custom pg-theme-swatch-bg"
                type="button"
                data-bg-custom-swatch
                aria-label="Custom background"
                aria-pressed="false"
                style="--pg-swatch: #eef1f4"
            >
                <span class="visually-hidden">Custom background</span>
                <i data-lucide="pipette" aria-hidden="true"></i>
            </button>
            <input class="pg-theme-color-input" type="color" value="#eef1f4" data-bg-custom aria-label="Pick a custom background colour">
        </label>
    </div>
</div>
