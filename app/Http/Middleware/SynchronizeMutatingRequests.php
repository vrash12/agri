<?php

namespace App\Http\Middleware;

use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\MunicipalityBoundary;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class SynchronizeMutatingRequests
{
    /**
     * Serialize mutations from one account to protect session/flash state, then
     * serialize writes to the same or related record across accounts. Other
     * create/import routes are grouped by municipality when one is available.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethodSafe()
            || ! $request->user()
        ) {
            return $next($request);
        }

        $userLock = Cache::lock(
            'mutating-user:v1:'.hash(
                'sha256',
                (string) $request->user()->getAuthIdentifier()
            ),
            max(10, (int) config('concurrency.write_lock_seconds', 120))
        );
        $lockSeconds = max(
            10,
            (int) config('concurrency.write_lock_seconds', 120)
        );
        $waitSeconds = max(
            1,
            (int) config('concurrency.write_wait_seconds', 5)
        );
        $resourceLocks = collect($this->lockKeys($request))
            ->map(fn (string $key) => Cache::lock($key, $lockSeconds))
            ->all();

        try {
            $run = fn () => $next($request);

            foreach (array_reverse($resourceLocks) as $resourceLock) {
                $nextRun = $run;
                $run = fn () => $resourceLock->block(
                    $waitSeconds,
                    $nextRun
                );
            }

            return $userLock->block(
                $waitSeconds,
                $run
            );
        } catch (LockTimeoutException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Another update is still being processed. Please retry in a moment.',
                ], 409);
            }

            return back()->with(
                'error',
                'Another update is still being processed. Please wait a moment and try again.'
            );
        }
    }

    /** @return array<int, string> */
    private function lockKeys(Request $request): array
    {
        $resources = collect($request->route()?->parameters() ?? [])
            ->filter(fn ($value) => $value instanceof Model)
            ->map(fn (Model $model) => get_class($model).':'.$model->getKey())
            ->values();

        if (str_starts_with((string) $request->route()?->getName(), 'municipality-boundaries.')) {
            $boundary = $request->route('boundary');
            $municipalityId = $boundary instanceof MunicipalityBoundary
                ? $boundary->municipality_id
                : $request->input('municipality_id');
            if ($municipalityId) {
                $resources->push(MunicipalityBoundary::class.':municipality:'.(int) $municipalityId);
            }
        }

        if (
            str_starts_with((string) $request->route()?->getName(), 'farm-plots.')
            || str_starts_with((string) $request->route()?->getName(), 'farmers.plots.')
        ) {
            $farmer = $request->route('farmer');
            $plot = $request->route('plot');
            $municipalityId = $farmer instanceof Farmer
                ? $farmer->municipality_id
                : ($plot instanceof FarmPlot ? $plot->farmer?->municipality_id : $request->input('municipality_id'));
            if ($municipalityId) {
                $resources->push(MunicipalityBoundary::class.':municipality:'.(int) $municipalityId);
            }
        }

        if ($request->filled('farmer_id')) {
            $resources->push(
                Farmer::class.':'.(int) $request->input('farmer_id')
            );
        }

        if ($request->filled('holder_id')) {
            $holderModel = $request->input('holder_type') === 'cooperative'
                ? FarmersCooperative::class
                : Farmer::class;
            $resources->push(
                $holderModel.':'.(int) $request->input('holder_id')
            );
        }

        $resources = $resources->filter(
            fn (string $resource) => ! str_ends_with($resource, ':0')
        )->unique()->sort()->values();

        if ($resources->isNotEmpty()) {
            return $resources
                ->map(fn (string $resource) => 'mutating-record:v2:'
                    .hash('sha256', $resource))
                ->all();
        }

        $route = $request->route()?->getName()
            ?: $request->method().':'.$request->path();
        $municipalityId = $request->input('municipality_id')
            ?: $request->user()->municipality_id;
        $scope = $municipalityId
            ? 'route:'.$route.'|municipality:'.$municipalityId
            : 'route:'.$route.'|user:'.$request->user()->getAuthIdentifier();

        return [
            'mutating-request:v2:'.hash('sha256', $scope),
        ];
    }
}
