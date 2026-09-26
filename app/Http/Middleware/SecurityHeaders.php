<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for everything Laravel serves. Uploaded photos under /storage are
 * served by Apache directly and never reach this; public/.htaccess covers them.
 *
 * A header a response already set is left alone, so a controller can loosen or tighten one.
 */
class SecurityHeaders
{
    /**
     * Browser features the app has no use for. Photo upload is a plain file input (its camera
     * option comes from the OS picker, which this does not govern), so camera can go too.
     */
    private const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), '
        .'serial=(), bluetooth=(), hid=(), midi=(), magnetometer=(), gyroscope=(), accelerometer=(), '
        .'display-capture=(), browsing-topics=()';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Nothing is meant to be framed by another site. SAMEORIGIN rather than DENY keeps
            // Livewire's own error modal (an iframe on the same page) working in development.
            'X-Frame-Options' => 'SAMEORIGIN',
            'Permissions-Policy' => self::PERMISSIONS_POLICY,
            'Content-Security-Policy' => config('security.csp'),
            'Content-Security-Policy-Report-Only' => config('security.csp_report_only'),
        ];

        // Only over HTTPS in production: browsers ignore HSTS on plain http anyway, and a local
        // or staging host should not be pinned. Set by vispa.itomat.se, includeSubDomains only
        // reaches *.vispa.itomat.se, never the rest of itomat.se. No `preload`: getting on the
        // browsers' built-in list is quick, getting off it takes months, so it is a one-way door.
        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (filled($value) && ! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
