<?php

namespace App\Modules\Procurement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every vendor-portal route behind the `procurement.vendor_portal_enabled`
 * flag. When off, the portal simply doesn't exist (404).
 */
class EnsureVendorPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('procurement.vendor_portal_enabled'), 404);

        return $next($request);
    }
}
