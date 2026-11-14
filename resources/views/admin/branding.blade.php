@extends('layouts.app')

@section('content')
    <section class="pg-page-intro">
        <div class="pg-page-intro-copy">
            <div class="pg-object">Administration</div>
            <h1 class="pg-page-title">Branding</h1>
            <p class="pg-page-lead">Customise the demo product name, plant label, logo and UI font for walkthroughs.</p>
        </div>
        <div class="pg-page-intro-actions">
            <a class="btn btn-pg-line" href="{{ route('admin.index') }}">Back to administration</a>
        </div>
    </section>

    @include('partials.flash')
    @include('admin.partials.branding-form')
@endsection
