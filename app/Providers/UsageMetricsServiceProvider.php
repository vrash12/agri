<?php

namespace App\Providers;

use App\Platform\Observability\UsageMetrics;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use WeakMap;

final class UsageMetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(UsageMetrics::class);
    }

    public function boot(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            $started = $event->request->attributes->get('_usage_started');
            if (is_int($started)) {
                app(UsageMetrics::class)->response((string) $event->request->route()?->getName(),
                    (hrtime(true) - $started) / 1000000, $event->response->getStatusCode());
            }
        });
        Event::listen(QueryExecuted::class, fn (QueryExecuted $event) => app(UsageMetrics::class)->query($event->time));
        $requests = new WeakMap;
        Event::listen(RequestSending::class, function (RequestSending $event) use ($requests): void {
            $provider = $this->provider($event->request->url());
            if ($provider !== null) {
                $requests[$event->request->toPsrRequest()] = [$provider, hrtime(true)];
                app(UsageMetrics::class)->providerAttempt($provider);
            }
        });
        $finished = function ($event, bool $failed) use ($requests): void {
            $request = $event->request->toPsrRequest();
            if (isset($requests[$request])) {
                [$provider, $started] = $requests[$request];
                unset($requests[$request]);
                app(UsageMetrics::class)->providerFinished($provider, (hrtime(true) - $started) / 1000000, $failed);
            }
        };
        Event::listen(ResponseReceived::class, fn (ResponseReceived $event) => $finished($event, $event->response->failed()));
        Event::listen(ConnectionFailed::class, fn (ConnectionFailed $event) => $finished($event, true));
        $this->app->terminating(fn () => app(UsageMetrics::class)->flush());
    }

    private function provider(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        return match (true) {
            $host === 'sh.dataspace.copernicus.eu' && $path === '/statistics/v1' => 'sentinel-statistics',
            $host === 'sh.dataspace.copernicus.eu' && $path === '/api/v1/process' => 'sentinel-image',
            $host === 'identity.dataspace.copernicus.eu' => 'sentinel-auth',
            $host === 'maps.googleapis.com' && $path === '/maps/api/staticmap' => 'google-static',
            in_array($host, ['api.open-meteo.com', 'customer-api.open-meteo.com'], true) => 'open-meteo',
            $host === 'climateserv.servirglobal.net' => 'chirps',
            default => null,
        };
    }
}
