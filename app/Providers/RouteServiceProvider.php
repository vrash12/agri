<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('planted-crop-layer', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Crop drawings are temporarily limited. Wait, then retry the remaining drawings.',
                    ], 429, $headers);
                });
        });

        foreach (['climate-risk-data' => 30, 'climate-risk-forecast' => 12, 'map-usage' => 60] as $name => $maximum) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($maximum)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        }

        // Numeric throttles share one account signature across unrelated routes.
        // Named satellite limits isolate searches, images and ordinary map work.
        foreach (['satellite-observations' => [6, 'picture searches'], 'satellite-images' => [12, 'picture loads']] as $name => [$maximum, $action]) {
            RateLimiter::for($name, function (Request $request) use ($maximum, $action) {
                return Limit::perMinute($maximum)
                    ->by($request->user()?->getAuthIdentifier() ?? $request->ip())
                    ->response(function (Request $request, array $headers) use ($action) {
                        $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

                        return response()->json([
                            'message' => 'Satellite '.$action.' are temporarily limited. Try again in '.$seconds.' seconds.',
                            'retry_after' => $seconds,
                        ], 429, $headers);
                    });
            });
        }
    }
}
