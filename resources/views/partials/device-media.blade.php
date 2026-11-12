@php
    $media = $media ?? null;
@endphp
@if ($media)
    <section class="pg-device-media {{ !empty($compact) ? 'is-compact' : '' }}">
        <div class="pg-device-media-visual">
            @if (!empty($media['image']))
                <img src="{{ $media['image'] }}" alt="{{ $media['label'] }}" loading="lazy">
            @else
                <div class="pg-device-media-fallback" aria-hidden="true">
                    <i data-lucide="cpu"></i>
                </div>
            @endif
        </div>
        <div class="pg-device-media-copy">
            <p class="pg-device-media-kicker">{{ $media['label'] }}</p>
            <p class="pg-device-media-summary">{{ $media['summary'] }}</p>
            @if (!empty($media['specs']))
                <dl class="pg-device-media-specs">
                    @foreach ($media['specs'] as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
            <p class="pg-device-media-note">Demo reference image for identification — not a live camera feed.</p>
        </div>
    </section>
@endif
