<?php

namespace App\Http\Middleware;

use App\Platform\Observability\UsageMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MeasureApplicationUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $metrics = app(UsageMetrics::class);
        $metrics->beginRequest();
        $request->attributes->set('_usage_started', hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        app(UsageMetrics::class)->flush();
    }
}
