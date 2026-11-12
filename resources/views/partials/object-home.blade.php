@php
    $tone = $tone ?? 'default';
    $eyebrow = $eyebrow ?? null;
    $lead = $lead ?? null;
@endphp
<section class="pg-object-home">
    <div class="pg-object-icon pg-object-icon--{{ $tone }}" aria-hidden="true">
        <i data-lucide="{{ $icon }}"></i>
    </div>
    <div class="pg-object-home-copy">
        @if ($eyebrow)
            <div class="pg-object">{{ $eyebrow }}</div>
        @endif
        <h1 class="pg-page-title">{{ $title }}</h1>
        @if ($lead)
            <p class="pg-page-lead">{{ $lead }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="pg-object-home-actions">
            {{ $actions }}
        </div>
    @endisset
</section>
