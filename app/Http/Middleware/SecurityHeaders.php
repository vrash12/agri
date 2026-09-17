<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apply the browser-side protections that do not depend on any single screen.
 *
 * The source lists below were taken from the scripts, styles, fonts, and map
 * tiles the interface actually loads. Adding a new CDN or provider to a Blade
 * view means adding its host here, otherwise the browser will block it.
 * `config/security.php` documents the switches for relaxing this in a hurry.
 */
class SecurityHeaders
{
    /**
     * Hosts that serve the interface's JavaScript.
     */
    private const SCRIPT_HOSTS = [
        'https://maps.googleapis.com',
        'https://maps.gstatic.com',
        'https://*.googleapis.com',
        'https://*.gstatic.com',
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
        'https://cdn.datatables.net',
        'https://code.jquery.com',
    ];

    /**
     * Hosts that serve the interface's stylesheets.
     */
    private const STYLE_HOSTS = [
        'https://fonts.googleapis.com',
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
        'https://cdn.datatables.net',
    ];

    /**
     * Hosts that serve map tiles, imagery, and icons.
     */
    private const IMAGE_HOSTS = [
        'https://*.googleapis.com',
        'https://*.gstatic.com',
        'https://*.google.com',
        'https://*.ggpht.com',
        'https://*.googleusercontent.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');

        // SAMEORIGIN, not DENY: the Backup Folder preview frames this application's
        // own file stream to show a PDF.
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $headers->set('Permissions-Policy', $this->permissionsPolicy());

        if ($request->isSecure() && config('security.hsts_max_age') > 0) {
            $headers->set('Strict-Transport-Security', $this->strictTransportSecurity());
        }

        // A response that already carries its own policy, such as a streamed
        // Backup Folder file, keeps the stricter one it set for itself.
        if (config('security.csp_enabled')
            && ! $headers->has('Content-Security-Policy')
            && ! $headers->has('Content-Security-Policy-Report-Only')) {
            $headers->set(
                config('security.csp_report_only')
                    ? 'Content-Security-Policy-Report-Only'
                    : 'Content-Security-Policy',
                $this->contentSecurityPolicy()
            );
        }

        return $response;
    }

    /**
     * Allow only the browser features the interface genuinely uses.
     */
    private function permissionsPolicy(): string
    {
        return implode(', ', [
            // The public land page offers to centre the map on the visitor.
            'geolocation=(self)',
            // The public map and the Backup Folder preview use these.
            'fullscreen=(self)',
            'clipboard-write=(self)',
            'accelerometer=()',
            'autoplay=()',
            'camera=()',
            'display-capture=()',
            'encrypted-media=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'usb=()',
        ]);
    }

    private function strictTransportSecurity(): string
    {
        $value = 'max-age='.(int) config('security.hsts_max_age');

        return config('security.hsts_include_subdomains')
            ? $value.'; includeSubDomains'
            : $value;
    }

    /**
     * The policy the interface runs under.
     *
     * `unsafe-inline` and `unsafe-eval` are required because the Blade views carry
     * inline scripts and styles and the Google Maps libraries compile at runtime.
     * Removing them means moving every inline block behind a per-request nonce,
     * which is a separate piece of work. Even with them, this policy still blocks
     * plugin content, base-tag injection, cross-site form posts, and framing by
     * another site.
     */
    private function contentSecurityPolicy(): string
    {
        $scripts = implode(' ', self::SCRIPT_HOSTS);
        $styles = implode(' ', self::STYLE_HOSTS);
        $images = implode(' ', self::IMAGE_HOSTS);

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: {$scripts}",
            "style-src 'self' 'unsafe-inline' {$styles}",
            "img-src 'self' data: blob: {$images}",
            'font-src \'self\' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net',
            "connect-src 'self' blob: https://*.googleapis.com https://*.gstatic.com https://*.google.com",
            "worker-src 'self' blob:",
            "child-src 'self' blob:",
            "frame-src 'self' blob:",
            "media-src 'self' blob:",
            "manifest-src 'self'",
        ]);
    }
}
