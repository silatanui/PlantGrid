@php
    $tone = $tone ?? 'info';
    $key = $key ?? null;
    $role = $role ?? ($tone === 'critical' ? 'alert' : 'status');
@endphp
<div
    class="pg-alert pg-alert-{{ $tone }}{{ ! empty($class) ? ' '.$class : '' }}"
    role="{{ $role }}"
    data-pg-alert
    @if ($key) data-alert-key="{{ $key }}" @endif
>
    <div class="pg-alert-body">{{ $slot }}</div>
    <button class="pg-alert-dismiss" type="button" data-pg-alert-dismiss aria-label="Dismiss alert">&times;</button>
</div>
