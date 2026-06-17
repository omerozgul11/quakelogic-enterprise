<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defense-in-depth HTTP security headers to every response:
 *  - Content-Security-Policy   — limits where scripts/styles/frames may load from
 *  - X-Frame-Options           — clickjacking protection (SAMEORIGIN keeps our
 *                                own same-origin file-preview iframes working)
 *  - X-Content-Type-Options    — stops MIME sniffing
 *  - Referrer-Policy           — don't leak full URLs to other origins
 *  - Permissions-Policy        — disable powerful browser APIs we don't use
 *  - Strict-Transport-Security — force HTTPS (only emitted over HTTPS)
 *
 * The CSP keeps 'unsafe-inline' for scripts/styles because the app bootstraps
 * via small inline blade scripts (theme + service worker) and Tailwind injects
 * inline styles; object-src/base-uri/form-action/frame-ancestors are still
 * locked down, which blocks the highest-impact injection vectors.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];
        if ($request->secure()) {
            $csp[] = 'upgrade-insecure-requests';
        }

        $headers = [
            'Content-Security-Policy' => implode('; ', $csp),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()',
        ];
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // Never clobber a header a specific response intentionally set.
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
