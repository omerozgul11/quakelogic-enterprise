<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;

Route::group(['middleware' => 'guest'], function () {
    Route::get('/login', fn() => inertia('Auth/Login'))->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/forgot-password', fn() => inertia('Auth/ForgotPassword'))->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', fn($token) => inertia('Auth/ResetPassword', ['token' => $token]))->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::group(['middleware' => 'auth'], function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/two-factor-challenge', fn() => inertia('Auth/TwoFactorChallenge'))->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store']);
});
