@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">{{ $system->name }}</h1>
    <p class="pg-page-lead">{{ $system->system_type }} · {{ $system->department->name }}</p>
    <section class="pg-card pg-card-pad my-3">
        <p>{{ $system->description }}</p>
        <dl class="pg-kv">
            <dt>Status</dt><dd>{!! \App\Support\Labels::badge($system->status) !!}</dd>
            <dt>Vendor</dt><dd>{{ $system->vendor->name ?? '-' }}</dd>
            <dt>Owner</dt><dd>{{ $system->owner->name ?? '-' }}</dd>
        </dl>
    </section>
    <section class="pg-card">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Related equipment</h2></div>
        <table class="pg-table">
            <tbody>
            @foreach ($system->equipment as $item)
                <tr><td>{{ $item->equipment_type }}</td><td class="pg-mono">{{ $item->asset->asset_tag ?? '' }}</td><td>{!! \App\Support\Labels::badge($item->status) !!}</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection
