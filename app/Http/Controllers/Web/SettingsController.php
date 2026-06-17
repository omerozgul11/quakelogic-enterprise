<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Mail\MailboxConnectionService;
use App\Services\Proposals\ProposalStyleService;
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
        'dashboard' => ['default_view' => 'personal', 'eur_usd_threshold' => 1.14],
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
            'mailbox' => app(MailboxConnectionService::class)->state($user->emailAccount),
        ]);
    }

    /** Validation rules shared by the connect form (self-service and admin). */
    public static function mailboxRules(): array
    {
        return [
            'email' => 'required|email',
            'from_name' => 'nullable|string|max:120',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|in:tls,ssl,none',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
        ];
    }

    /**
     * Connect (or update) the current user's own work email over SMTP. The app
     * password is required to connect and optional on later edits (left blank
     * keeps the stored one).
     */
    public function connectMailbox(Request $request, MailboxConnectionService $mailboxes): RedirectResponse
    {
        $validated = $request->validate(self::mailboxRules());

        $account = $mailboxes->connect($request->user(), $validated);
        if ($account === null) {
            return back()->withErrors(['smtp_password' => 'An app password is required to connect your email.']);
        }

        return back()->with('success', 'Work email saved. Send a test email to confirm it can send.');
    }

    /** Send a test email to the user's own address to verify the SMTP settings. */
    public function testMailbox(Request $request, MailboxConnectionService $mailboxes): RedirectResponse
    {
        $user = $request->user();

        if (!$mailboxes->mailbox($user)?->isConnected()) {
            return back()->with('error', 'Connect your work email first.');
        }

        return $mailboxes->test($user)
            ? back()->with('success', "Test email sent to {$user->email}. Check your inbox.")
            : back()->with('error', 'Could not send — double-check the host, port, encryption and app password.');
    }

    public function disconnectMailbox(Request $request, MailboxConnectionService $mailboxes): RedirectResponse
    {
        $mailboxes->disconnect($request->user());

        return back()->with('success', 'Work email disconnected.');
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

    /**
     * Org-wide proposal Style Profile (admin only) — the tone, boilerplate,
     * win themes, writing rules and document formatting the AI writer follows.
     */
    public function proposalStyle(Request $request, ProposalStyleService $styles): Response
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403);
        $org = Organization::findOrFail($request->user()->organization_id);

        return Inertia::render('Settings/ProposalStyle', [
            'style' => $styles->get($org),
        ]);
    }

    public function updateProposalStyle(Request $request, ProposalStyleService $styles): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Super Admin'), 403);

        $validated = $request->validate([
            'tone' => 'nullable|string|max:500',
            'voice' => 'nullable|string|max:255',
            'company_background' => 'nullable|string|max:6000',
            'win_themes' => 'nullable|string|max:3000',
            'writing_rules' => 'nullable|string|max:6000',
            'format' => 'nullable|array',
            'format.font' => 'nullable|string|max:60',
            'format.heading_font' => 'nullable|string|max:60',
            'format.font_size' => 'nullable|numeric|min:6|max:24',
            'format.line_spacing' => 'nullable|numeric|min:1|max:3',
            'format.margin_inches' => 'nullable|numeric|min:0.25|max:2',
            'format.accent_color' => 'nullable|string|max:7',
        ]);

        $styles->save(Organization::findOrFail($request->user()->organization_id), $validated);

        return back()->with('success', 'Proposal style profile saved.');
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
            'dashboard.eur_usd_threshold' => 'nullable|numeric|min:0|max:100',
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
