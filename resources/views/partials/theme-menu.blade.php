@php
    $align = $align ?? 'end';
    $buttonClass = $buttonClass ?? 'pg-theme-menu-toggle';
    $menuClass = $menuClass ?? '';
@endphp
<div class="dropdown pg-theme-menu">
    <button
        class="{{ $buttonClass }}"
        type="button"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        aria-haspopup="true"
        aria-label="Appearance"
        title="Appearance"
    >
        <i data-lucide="palette" aria-hidden="true"></i>
        <span class="pg-theme-menu-label">Appearance</span>
    </button>
    <div class="dropdown-menu dropdown-menu-{{ $align }} pg-theme-menu-panel {{ $menuClass }}">
        <div class="pg-theme-menu-head">Appearance</div>
        <p class="pg-theme-menu-lead">Theme accent and dashboard background for this browser.</p>
        @include('partials.theme-picker')
    </div>
</div>
