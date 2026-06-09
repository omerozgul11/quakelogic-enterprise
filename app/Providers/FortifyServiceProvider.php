<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = str($request->input(Fortify::username()) . '|' . $request->ip())->lower();
            return Limit::perMinute(10)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
