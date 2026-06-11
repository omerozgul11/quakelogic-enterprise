<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const DEFAULT_PREFERENCES = [
        'display' => ['theme' => 'system', 'density' => 'comfortable'],
        'dashboard' => ['default_view' => 'personal'],
        'channels' => [
            'new_proposal' => true,
            'new_opportunity' => true,
            'desktop' => true,
            'sound' => true,
        ],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'user' => $user->only(['id', 'name', 'email', 'commission_rate_override']),
            'preferences' => self::mergedPreferences($user->notification_preferences),
            'twoFactor' => [
                'enabled' => !is_null($user->two_factor_secret),
                'confirmed' => !is_null($user->two_factor_confirmed_at),
            ],
            'mailbox' => [
                'connected' => (bool) $user->emailAccount?->isConnected(),
                'email' => $user->emailAccount?->email,
                // True once Google Workspace OAuth credentials are configured.
                'configurable' => (bool) config('services.google.client_id'),
            ],
        ]);
    }

    public static function mergedPreferences(?array $stored): array
    {
        $stored = $stored ?? [];
        return [
            'display' => array_merge(self::DEFAULT_PREFERENCES['display'], $stored['display'] ?? []),
            'dashboard' => array_merge(self::DEFAULT_PREFERENCES['dashboard'], $stored['dashboard'] ?? []),
            'channels' => array_merge(self::DEFAULT_PREFERENCES['channels'], $stored['channels'] ?? []),
        ];
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
        ]);

        $request->user()->update($validated);
        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $request->user()->update(['password' => Hash::make($validated['password'])]);
        return back()->with('success', 'Password changed.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display.theme' => 'required|in:system,light,dark',
            'display.density' => 'required|in:comfortable,compact',
            'dashboard.default_view' => 'required|in:personal,executive',
            'channels.new_proposal' => 'boolean',
            'channels.new_opportunity' => 'boolean',
            'channels.desktop' => 'boolean',
            'channels.sound' => 'boolean',
        ]);

        $user = $request->user();
        $merged = self::mergedPreferences($user->notification_preferences);
        $merged = [
            'display' => array_merge($merged['display'], Arr::get($validated, 'display', [])),
            'dashboard' => array_merge($merged['dashboard'], Arr::get($validated, 'dashboard', [])),
            'channels' => array_merge($merged['channels'], Arr::get($validated, 'channels', [])),
        ];

        $user->update(['notification_preferences' => $merged]);

        return back()->with('success', 'Preferences saved.');
    }
}
