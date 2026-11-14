@extends('layouts.app')

@section('content')
    <section class="pg-object-home">
        <div class="pg-object-icon pg-object-icon--docs" aria-hidden="true">
            <i data-lucide="book-open"></i>
        </div>
        <div class="pg-object-home-copy">
            <div class="pg-object">Help</div>
            <h1 class="pg-page-title">Documentation</h1>
            <p class="pg-page-lead">Start with the novice guides if you are new, then use the step-by-step templates for cases, notes, ownership, duplicates, escalations and the Assistant.</p>
        </div>
    </section>

    <form class="pg-docs-search mb-3" method="get" action="{{ route('docs.index') }}">
        <label class="visually-hidden" for="docs-q">Search documentation</label>
        <input class="form-control" id="docs-q" name="q" value="{{ $term }}" placeholder="Search guides - getting started, report issue, SLA…">
        <button class="btn btn-pg" type="submit">Search</button>
    </form>

    <div class="pg-card table-responsive">
        <table class="pg-table pg-docs-table">
            <thead>
                <tr>
                    <th>Guide</th>
                    <th>Audience</th>
                    <th>Summary</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($docs as $doc)
                    <tr>
                        <td>
                            <a href="{{ route('docs.show', $doc['slug']) }}">{{ $doc['title'] }}</a>
                        </td>
                        <td class="pg-docs-audience">{{ $doc['audience'] }}</td>
                        <td class="text-muted">{{ $doc['summary'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-muted">No guides matched “{{ $term }}”.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
