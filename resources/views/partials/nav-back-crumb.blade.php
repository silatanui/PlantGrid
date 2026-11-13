@php
    $navBackCrumb = $navPrevious ?? app(\App\Services\NavigationHistory::class)->previous(request());
@endphp
@if (!empty($navBackCrumb))
    <p class="pg-nav-back-crumb mb-2">
        <a href="{{ $navBackCrumb['url'] }}" title="{{ $navBackCrumb['path'] }}">
            <i data-lucide="arrow-left"></i>
            Back to {{ $navBackCrumb['label'] }}
        </a>
    </p>
@endif
