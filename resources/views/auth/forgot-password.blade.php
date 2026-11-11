@extends('layouts.guest')

@section('content')
    <div class="pg-auth-shell pg-auth-shell-simple">
        <main class="pg-auth-panel">
            <section class="pg-auth-card">
                <header class="pg-auth-card-head">
                    <p class="pg-object">Account recovery</p>
                    <h1 class="pg-page-title">Forgot password</h1>
                    <p class="pg-page-lead">Enter your PlantGrid email. If the account exists, a reset link is sent (check the mail log on this demo).</p>
                </header>
                @include('partials.flash')
                <form class="pg-auth-form" method="post" action="{{ route('password.email') }}">
                    @csrf
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control mb-3" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                    <button class="btn btn-pg w-100" type="submit">Send reset link</button>
                </form>
                <p class="pg-auth-back mt-3">
                    <a href="{{ route('login') }}"><i data-lucide="arrow-left"></i> Back to sign in</a>
                </p>
            </section>
        </main>
    </div>
@endsection
