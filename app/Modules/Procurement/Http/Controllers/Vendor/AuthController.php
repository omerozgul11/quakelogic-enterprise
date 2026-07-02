<?php

namespace App\Modules\Procurement\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\SupplierContact;
use App\Modules\Procurement\Support\VendorPortalAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Vendor-portal authentication — a self-contained session login for supplier
 * contacts, isolated from the staff guard. Throttled to blunt credential
 * stuffing; identical error for unknown email / bad password / disabled portal
 * so the form never reveals which accounts exist.
 */
class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (VendorPortalAuth::contact()?->canUsePortal()) {
            return redirect()->route('vendor.dashboard');
        }

        return view('vendor.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = 'vendor-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['email' => "Too many attempts. Please try again in {$seconds} seconds."])->onlyInput('email');
        }

        // Email isn't unique across suppliers, so match on the first contact
        // whose portal is usable and whose password verifies.
        $contact = SupplierContact::with('supplier')
            ->where('email', $data['email'])
            ->get()
            ->first(fn (SupplierContact $c) => $c->canUsePortal() && Hash::check($data['password'], $c->portal_password));

        if (! $contact) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['email' => 'Those credentials do not match our records.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        VendorPortalAuth::login($contact);
        $contact->forceFill(['portal_last_login_at' => now()])->save();

        return redirect()->route('vendor.dashboard');
    }

    public function logout(): RedirectResponse
    {
        VendorPortalAuth::logout();

        return redirect()->route('vendor.login')->with('success', 'You have been signed out.');
    }
}
