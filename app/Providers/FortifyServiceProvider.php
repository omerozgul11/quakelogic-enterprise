<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse {
            public function toResponse($request)
            {
                return redirect('/login');
            }
        });
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                if (!$user->is_active) {
                    return null;
                }
                return $user;
            }
        });

        Fortify::loginView(fn() => inertia('Auth/Login'));
        Fortify::requestPasswordResetLinkView(fn() => inertia('Auth/ForgotPassword'));
        Fortify::resetPasswordView(fn($request) => inertia('Auth/ResetPassword', ['token' => $request->route('token'), 'email' => $request->email]));
        Fortify::twoFactorChallengeView(fn() => inertia('Auth/TwoFactorChallenge'));

        // Brute-force protection: two layered limits. The first caps attempts
        // against a single account from one IP (stops password guessing); the
        // second caps total attempts from one IP across many accounts (stops
        // credential stuffing / username spraying).
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input(Fortify::username());
            $perAccount = str($email . '|' . $request->ip())->lower();

            return [
                Limit::perMinute(5)->by($perAccount),
                Limit::perMinute(30)->by('ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Audit trail for attacks: log every failed credential check. A burst of
        // these from one IP/account is the brute-force signal (the throttle then
        // returns HTTP 429 once the 'login' limiter trips).
        Event::listen(Failed::class, function (Failed $event) {
            Log::channel('stack')->warning('Failed login attempt', [
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }
}
