<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private ImpersonationService $impersonation) {}

    /** Start impersonating a user (Super Admin only — route is role-gated). */
    public function start(Request $request, User $user): RedirectResponse
    {
        $this->impersonation->start($request, $request->user(), $user);

        return redirect('/')->with('success', "You are now viewing the app as {$user->name}.");
    }

    /**
     * Stop impersonating and return to the original admin account. Reachable
     * while NOT a Super Admin (the active session user is the impersonated one).
     */
    public function stop(Request $request): RedirectResponse
    {
        $admin = $this->impersonation->stop($request);

        if ($admin) {
            return redirect()->route('admin.users')->with('success', 'Welcome back — you have returned to your account.');
        }

        return redirect('/');
    }
}
