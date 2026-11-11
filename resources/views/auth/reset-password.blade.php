@extends('layouts.guest')

@section('content')
    <div class="pg-auth-shell pg-auth-shell-simple">
        <main class="pg-auth-panel">
            <section class="pg-auth-card">
                <header class="pg-auth-card-head">
                    <p class="pg-object">Account recovery</p>
                    <h1 class="pg-page-title">Choose a new password</h1>
                </header>
                @include('partials.flash')
                <form class="pg-auth-form" method="post" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control mb-2" id="email" name="email" type="email" value="{{ old('email', $email) }}" required>
                    <label class="form-label" for="password">New password</label>
                    <input class="form-control mb-2" id="password" name="password" type="password" required>
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input class="form-control mb-3" id="password_confirmation" name="password_confirmation" type="password" required>
                    <button class="btn btn-pg w-100" type="submit">Update password</button>
                </form>
                <p class="pg-auth-back mt-3">
                    <a href="{{ route('login') }}"><i data-lucide="arrow-left"></i> Back to sign in</a>
                </p>
            </section>
        </main>
    </div>
@endsection
