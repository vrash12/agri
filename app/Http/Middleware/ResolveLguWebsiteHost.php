<?php

namespace App\Http\Middleware;

use App\Modules\LocalGovernment\Application\LguWebsiteAddress;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveLguWebsiteHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = LguWebsiteAddress::domain();
        if (! $domain || $request->getHost() === LguWebsiteAddress::applicationHost()) {
            return $next($request);
        }

        // A hostname selects public content only. Never run office, API or farmer
        // authentication routes on a tenant host, including unpublished hosts.
        abort_unless(preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)\.'.preg_quote($domain, '/').'$/D', $request->getHost(), $matches), 404);
        abort_if(in_array($matches[1], LguWebsiteAddress::reservedSlugs(), true), 404);
        abort_unless(in_array($request->method(), ['GET', 'HEAD'], true)
            && in_array($request->path(), ['/', 'lgu-media/logo', 'lgu-media/cover'], true), 404);
        $request->attributes->set('lgu_website_slug', $matches[1]);

        return $next($request);
    }
}
