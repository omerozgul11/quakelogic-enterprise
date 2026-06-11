<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'model' => User::class,
    'prefix' => '',
    'domain' => null,
    'home' => '/',
    'lowercase_usernames' => false,
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    'views' => true,
    'features' => [
        // Public self-registration is intentionally DISABLED: accounts are
        // provisioned by an administrator from the Admin panel only. Re-enable
        // Features::registration() here if open sign-up is ever required.
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            // Require a TOTP code to activate (prevents lock-out), but not a
            // password re-confirmation step — keeps the in-app Inertia flow clean
            // for this optional, user-managed feature.
            'confirm' => true,
            'confirmPassword' => false,
            'window' => 1,
        ]),
    ],
    'password_broker' => 'users',
    'username' => 'email',
    'email' => 'email',
    'redirects' => [],
];
