<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdentityService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function create(Request $request, IdentityService $identity)
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'title' => 'Sign in',
            'entraEnabled' => $identity->entraEnabled(),
            'entraConfigured' => filled($identity->entraClientId()),
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in a minute.',
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || $user->status !== 'active' || ! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            $audit->log('auth.failed', 'user', $user?->id, [], ['email' => $credentials['email']]);

            throw ValidationException::withMessages([
                'email' => 'Those sign-in details do not match an active account.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.login', 'user', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit)
    {
        $audit->log('auth.logout', 'user', $request->user()?->id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgotCreate()
    {
        return view('auth.forgot-password', [
            'title' => 'Reset password',
        ]);
    }

    public function forgotStore(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function resetCreate(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'title' => 'Choose a new password',
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetStore(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }

    public function entraRedirect(Request $request, IdentityService $identity)
    {
        if (! $identity->entraEnabled()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Microsoft Entra ID is not enabled. Configure it under Administration → Identity.',
            ]);
        }

        $state = Str::random(40);
        $request->session()->put('entra_oauth_state', $state);

        return redirect()->away($identity->authorizeUrl($state));
    }

    public function entraCallback(Request $request, IdentityService $identity, AuditLogger $audit)
    {
        if (! $identity->entraEnabled()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Microsoft Entra ID is not enabled.',
            ]);
        }

        if ($request->string('state')->toString() !== $request->session()->pull('entra_oauth_state')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Microsoft sign-in state was invalid. Try again.',
            ]);
        }

        if ($request->filled('error')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Microsoft sign-in was cancelled or denied.',
            ]);
        }

        $code = $request->string('code')->toString();
        if ($code === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Microsoft did not return an authorization code.',
            ]);
        }

        $tokenResponse = Http::asForm()->post($identity->tokenEndpoint(), [
            'client_id' => $identity->entraClientId(),
            'client_secret' => $identity->entraClientSecret(),
            'code' => $code,
            'redirect_uri' => $identity->entraRedirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenResponse->successful()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Could not exchange the Microsoft authorization code.',
            ]);
        }

        $accessToken = $tokenResponse->json('access_token');
        $profile = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://graph.microsoft.com/v1.0/me');

        if (! $profile->successful()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Could not read the Microsoft profile.',
            ]);
        }

        $email = strtolower(trim((string) (
            $profile->json('mail')
            ?: $profile->json('userPrincipalName')
            ?: ''
        )));

        if ($email === '') {
            return redirect()->route('login')->withErrors([
                'email' => 'Microsoft profile did not include an email address.',
            ]);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user || $user->status !== 'active') {
            $audit->log('auth.entra.denied', 'user', $user?->id, [], ['email' => $email]);

            return redirect()->route('login')->withErrors([
                'email' => 'No active PlantGrid account matches '.$email.'. SSO does not auto-create users in this demo.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->log('auth.entra.login', 'user', $user->id);

        return redirect()->intended(route('dashboard'));
    }
}
