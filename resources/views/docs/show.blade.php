@extends('layouts.app')

@section('content')
    <p class="mb-2">
        <a href="{{ route('docs.index') }}" class="pg-docs-back">
            <i data-lucide="arrow-left"></i>
            All documentation
        </a>
    </p>

    <article class="pg-docs-article">
        <header class="pg-docs-article-head">
            <div class="pg-object">Template</div>
            <h1 class="pg-page-title">{{ $doc['title'] }}</h1>
            <p class="pg-page-lead">{{ $doc['summary'] }}</p>
            <p class="text-muted small mb-0">Audience: {{ $doc['audience'] }}</p>
        </header>

        @foreach ($doc['sections'] as $section)
            <section class="pg-docs-section">
                <h2>{{ $section['heading'] }}</h2>
                <div class="pg-docs-body">{!! nl2br(e($section['body'])) !!}</div>
            </section>
        @endforeach

        @if (! empty($doc['screenshots']))
            <section class="pg-docs-section">
                <h2>Screenshots</h2>
                <div class="pg-docs-shots">
                    @foreach ($doc['screenshots'] as $shot)
                        @php $path = 'images/docs/'.$shot['file']; @endphp
                        <figure class="pg-docs-shot">
                            @if (file_exists(public_path($path)))
                                <img src="{{ asset($path) }}?v={{ @filemtime(public_path($path)) ?: 1 }}" alt="{{ $shot['caption'] }}">
                            @else
                                <div class="pg-docs-shot-fallback" role="img" aria-label="{{ $shot['caption'] }}">
                                    <strong>{{ $doc['title'] }}</strong>
                                    <span>{{ $shot['caption'] }}</span>
                                </div>
                            @endif
                            <figcaption>{{ $shot['caption'] }}</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif
    </article>

    @if ($related->isNotEmpty())
        <section class="mt-4">
            <h2 class="pg-section-title">Related guides</h2>
            <div class="pg-card table-responsive">
                <table class="pg-table pg-docs-table">
                    <thead>
                        <tr>
                            <th>Guide</th>
                            <th>Audience</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($related as $item)
                            <tr>
                                <td><a href="{{ route('docs.show', $item['slug']) }}">{{ $item['title'] }}</a></td>
                                <td class="pg-docs-audience">{{ $item['audience'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
