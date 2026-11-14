@php
    $class = $class ?? 'pg-logo';
    $brandName = $brandName ?? ($branding['brandName'] ?? 'PlantGrid');
    $title = $title ?? ($brandName.', Manufacturing Technology Management');
    $logoUrl = $brandLogoUrl ?? ($branding['brandLogoUrl'] ?? null);
@endphp
<span class="{{ $class }}" aria-hidden="false">
    @if ($logoUrl)
        <img
            class="pg-logo-img"
            src="{{ $logoUrl }}"
            alt="{{ $brandName }}"
            title="{{ $title }}"
        >
    @else
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 1200 260"
             role="img"
             aria-label="{{ $title }}">
            <title>{{ $title }}</title>
            <g fill="var(--pg-logo-fill, currentColor)">
                <path d="M 0,250 L 120,10 L 205,10 L 85,250 Z"/>
                <path d="M 280,10 H 1160 Q 1195,10 1180,45 L 1095,215 Q 1085,250 1050,250 H 125 L 245,30 Q 257,10 280,10 Z"/>
            </g>
            <text x="690" y="178"
                  fill="var(--pg-logo-ink, #ffffff)"
                  font-family="Arial, Helvetica, sans-serif"
                  font-size="{{ strlen($brandName) > 12 ? 120 : 158 }}"
                  font-weight="700"
                  letter-spacing="-7"
                  text-anchor="middle">{{ \Illuminate\Support\Str::limit($brandName, 18, '') }}</text>
        </svg>
    @endif
</span>
