@props([
    'column',
    'label',
    'sort',
    'routeName' => null,
])

@php
    $active = $sort->is($column);
    $nextDirection = $active && $sort->direction === 'asc' ? 'desc' : 'asc';
    $query = array_merge(request()->query(), [
        'sort' => $column,
        'direction' => $nextDirection,
    ]);
    $href = $routeName
        ? route($routeName, $query)
        : request()->fullUrlWithQuery([
            'sort' => $column,
            'direction' => $nextDirection,
        ]);
@endphp

<th {{ $attributes->class(['pg-sort-th', 'is-sorted' => $active]) }}>
    <a
        class="pg-sort-link"
        href="{{ $href }}"
        aria-sort="{{ $active ? ($sort->direction === 'asc' ? 'ascending' : 'descending') : 'none' }}"
    >
        <span>{{ $label }}</span>
        <span class="pg-sort-icon" aria-hidden="true">
            @if ($active)
                {{ $sort->direction === 'asc' ? '↑' : '↓' }}
            @else
                ↕
            @endif
        </span>
    </a>
</th>
