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
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            'window' => 0,
        ]),
    ],
    'password_broker' => 'users',
    'username' => 'email',
    'email' => 'email',
    'redirects' => [],
];
