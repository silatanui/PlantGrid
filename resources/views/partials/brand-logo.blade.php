@php
    $class = $class ?? 'pg-logo';
    $title = $title ?? 'PlantGrid';
@endphp
<span class="{{ $class }}" aria-hidden="false">
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
              font-size="158"
              font-weight="700"
              letter-spacing="-7"
              text-anchor="middle">PlantGrid</text>
    </svg>
</span>
