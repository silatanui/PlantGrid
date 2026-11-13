@extends('layouts.app')

@section('content')
    <h1 class="pg-page-title">Search</h1>
    <p class="pg-page-lead">@if ($term === '') Type a person, tag, ticket number, hostname, page name, or “My cases”. @else Results for “{{ $term }}”. @endif</p>

    <section class="pg-card my-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Pages</h2></div>
        <table class="pg-table">
            <tbody>
            @forelse ($pages as $page)
                <tr>
                    <td>
                        <a href="{{ $page['url'] }}">{{ $page['title'] }}</a>
                        <div class="text-muted small">{{ $page['summary'] }}</div>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-pg" href="{{ $page['url'] }}">Open</a>
                    </td>
                </tr>
            @empty
                <tr><td class="text-muted">No pages matched.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="pg-card my-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">People</h2></div>
        <table class="pg-table">
            <tbody>
            @forelse ($people as $person)
                <tr>
                    <td>
                        <a href="{{ route('people.show', $person) }}">{{ $person->name }}</a>
                        <div class="text-muted small">{{ $person->job_title ?: $person->roleLabel() }} · {{ $person->email }}</div>
                        <div class="text-muted small">{{ $person->contactLine() }}</div>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                            <a class="btn btn-sm btn-pg-line" href="{{ route('people.performance', $person) }}">Performance</a>
                            <a class="btn btn-sm btn-pg" href="{{ route('people.show', $person) }}#contact-details">Contact</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td class="text-muted">No people matched.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="pg-card my-3">
        <div class="pg-card-pad"><h2 class="pg-section-title mb-0">Documentation</h2></div>
        <table class="pg-table">
            <tbody>
            @forelse ($docs as $doc)
                <tr>
                    <td>
                        <a href="{{ route('docs.show', $doc['slug']) }}">{{ $doc['title'] }}</a>
                        <div class="text-muted small">{{ $doc['summary'] }}</div>
                    </td>
                </tr>
            @empty
                <tr><td class="text-muted">No documentation matched.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    @php
        $caseHeading = \App\Support\SearchPages::caseListIntent($term)['label'] ?? 'Cases';
    @endphp
    @foreach ([$caseHeading => $tickets, 'Assets' => $assets, 'Servers' => $servers, 'Network devices' => $devices] as $heading => $items)
        <section class="pg-card my-3">
            <div class="pg-card-pad"><h2 class="pg-section-title mb-0">{{ $heading }}</h2></div>
            <table class="pg-table">
                <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>
                            @if ($heading === 'Assets')
                                <a href="{{ route('assets.show', $item) }}">{{ $item->asset_tag }}</a> {{ $item->name }}
                            @elseif (str_contains($heading, 'cases') || str_contains($heading, 'Cases') || $heading === 'Assigned to me')
                                <a href="{{ route('helpdesk.show', $item) }}">{{ $item->ticket_number }}</a> {{ $item->subject }}
                                <div class="text-muted small">{{ \App\Support\Labels::status((string) $item->status) }}</div>
                            @elseif ($heading === 'Servers')
                                <a class="pg-mono" href="{{ route('servers.show', $item) }}">{{ $item->hostname }}</a>
                            @else
                                <a class="pg-mono" href="{{ route('network.show', $item) }}">{{ $item->hostname }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="text-muted">Nothing in this group.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endforeach
@endsection
