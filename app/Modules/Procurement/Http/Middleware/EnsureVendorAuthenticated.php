<?php

namespace App\Modules\Procurement\Http\Middleware;

use App\Modules\Procurement\Support\VendorPortalAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a signed-in vendor contact whose portal access is still valid. On
 * failure the session is cleared and the visitor is sent to the vendor login.
 * The resolved contact is stashed on the request for controllers to use.
 */
class EnsureVendorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $contact = VendorPortalAuth::contact();

        if (! $contact || ! $contact->canUsePortal()) {
            VendorPortalAuth::logout();

            return redirect()->route('vendor.login')->with('error', 'Please sign in to continue.');
        }

        $request->attributes->set('vendorContact', $contact);

        return $next($request);
    }
}
