<?php

namespace App\Services\Auth;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "Login as" impersonation. Session-based on the web guard: the original
 * admin's id is stashed in the session so the user can return with one click.
 * Every guard here is enforced server-side (the UI hides the button too, but
 * that's cosmetic).
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';
    public const LOG_KEY = 'impersonation_log_id';

    public function isImpersonating(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    /**
     * Begin impersonating $target as $admin. Aborts 403 on any guard violation:
     * lacks permission, already impersonating, self, cross-organization,
     * deactivated target, or another Super Admin.
     */
    public function start(Request $request, User $admin, User $target): void
    {
        abort_unless($admin->can('impersonate users'), 403, 'You are not allowed to impersonate users.');
        abort_if($this->isImpersonating($request), 403, 'You are already impersonating a user.');
        abort_if($target->id === $admin->id, 403, 'You cannot impersonate yourself.');
        abort_unless($target->organization_id === $admin->organization_id, 403, 'You can only impersonate users in your organization.');
        abort_unless((bool) $target->is_active, 403, 'You cannot impersonate a deactivated user.');
        abort_if($target->hasRole('Super Admin'), 403, 'You cannot impersonate another Super Admin.');

        $log = ImpersonationLog::create([
            'organization_id' => $admin->organization_id,
            'impersonator_id' => $admin->id,
            'impersonated_id' => $target->id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        // Stash BEFORE switching — Auth::login() migrates the session (regenerates
        // the id, preserves the data), so these keys survive the switch.
        $request->session()->put(self::SESSION_KEY, $admin->id);
        $request->session()->put(self::LOG_KEY, $log->id);

        Auth::guard('web')->login($target);
    }

    /**
     * Stop impersonating and restore the original admin. No-op (returns null) if
     * not currently impersonating. Returns the restored admin user.
     */
    public function stop(Request $request): ?User
    {
        if (! $this->isImpersonating($request)) {
            return null;
        }

        $adminId = (int) $request->session()->get(self::SESSION_KEY);
        $logId = $request->session()->get(self::LOG_KEY);

        if ($logId && ($log = ImpersonationLog::find($logId)) && $log->ended_at === null) {
            $log->update(['ended_at' => now()]);
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->forget(self::LOG_KEY);

        $admin = User::find($adminId);
        if ($admin) {
            Auth::guard('web')->login($admin);
        } else {
            Auth::guard('web')->logout();
            $request->session()->regenerate();
        }

        return $admin;
    }
}
