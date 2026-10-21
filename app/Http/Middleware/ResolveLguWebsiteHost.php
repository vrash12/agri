<?php

namespace App\Http\Middleware;

use App\Modules\LocalGovernment\Application\LguWebsiteAddress;
use App\Modules\LocalGovernment\Application\PublishedLguWebsite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ResolveLguWebsiteHost
{
    public function __construct(private PublishedLguWebsite $websites) {}

    public function handle(Request $request, Closure $next): Response
    {
        $domain = LguWebsiteAddress::domain();
        if (! $domain || $request->getHost() === LguWebsiteAddress::applicationHost()) {
            return $next($request);
        }

        // A hostname selects public content only. Never process credentials or
        // operational requests on a tenant, including unpublished hosts.
        abort_unless(preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.'.preg_quote($domain, '/').'$/D', $request->getHost(), $matches), 404);
        abort_if(in_array($matches[1], LguWebsiteAddress::reservedSlugs(), true), 404);
        if (in_array($request->method(), ['GET', 'HEAD'], true) && $request->path() === 'login') {
            // This redirect runs before routing/sessions; bound its publication
            // lookup independently from the credential-attempt limits.
            $key = 'lgu-sign-in-entry:'.hash('sha256', (string) $request->ip());
            if (RateLimiter::tooManyAttempts($key, 60)) {
                return response('Too many sign-in page requests. Please try again shortly.', 429,
                    ['Retry-After' => (string) RateLimiter::availableIn($key), 'Cache-Control' => 'private, no-store']);
            }
            RateLimiter::hit($key, 60);
            $website = $this->websites->find($matches[1]);

            return redirect()->to(LguWebsiteAddress::centralUrl('lgu.login', ['slug' => $website->slug]))
                ->header('Cache-Control', 'private, no-store');
        }
        abort_unless(in_array($request->method(), ['GET', 'HEAD'], true)
            && in_array($request->path(), ['/', 'lgu-media/logo', 'lgu-media/cover'], true), 404);
        $request->attributes->set('lgu_website_slug', $matches[1]);

        return $next($request);
    }
}
