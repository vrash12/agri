<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\User;
use App\Services\WeatherForecastService;
use App\Support\MunicipalityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WeatherAdvisoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(
        Request $request,
        MunicipalityAccess $municipalityAccess
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $municipalities = $municipalityAccess->choices($user);
        $municipality = $this->selectedMunicipality(
            $request,
            $user,
            $municipalities
        );

        return redirect()->to(route('farmers.index', [
            'municipality_id' => $municipality->id,
            'show_weather' => 1,
        ]).'#farmersMapModule');
    }

    public function summary(
        Request $request,
        MunicipalityAccess $municipalityAccess,
        WeatherForecastService $weather
    ): JsonResponse {
        return $this->summaryResponse(
            $request,
            $municipalityAccess,
            $weather,
            false
        );
    }

    public function refreshSummary(
        Request $request,
        MunicipalityAccess $municipalityAccess,
        WeatherForecastService $weather
    ): JsonResponse {
        return $this->summaryResponse(
            $request,
            $municipalityAccess,
            $weather,
            true
        );
    }

    public function refresh(
        Request $request,
        MunicipalityAccess $municipalityAccess,
        WeatherForecastService $weather
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $municipalities = $municipalityAccess->choices($user);
        $municipality = $this->selectedMunicipality(
            $request,
            $user,
            $municipalities
        );

        $forecast = $weather->forMunicipality($municipality, true);

        return redirect()->to(route('farmers.index', [
            'municipality_id' => $municipality->id,
            'show_weather' => 1,
        ]).'#farmersMapModule')->with(
                $forecast['available'] ? 'success' : 'error',
                $forecast['available']
                    ? 'Weather forecast refreshed.'
                    : (string) $forecast['status_message']
            );
    }

    private function summaryResponse(
        Request $request,
        MunicipalityAccess $municipalityAccess,
        WeatherForecastService $weather,
        bool $forceRefresh
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $municipalities = $municipalityAccess->choices($user);
        $municipality = $this->selectedMunicipality(
            $request,
            $user,
            $municipalities
        );
        $forecast = $weather->forMunicipality($municipality, $forceRefresh);

        return response()->json([
            'selected_municipality' => [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'province' => $municipality->province ?: 'Tarlac',
            ],
            'can_choose_municipality' => $user->canAccessAllMunicipalities(),
            'municipalities' => $municipalities
                ->map(fn (Municipality $choice) => [
                    'id' => $choice->id,
                    'name' => $choice->name,
                ])
                ->values(),
            'forecast' => $forecast,
            'official_links' => (array) config('weather.pagasa', []),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Municipality>  $municipalities
     */
    private function selectedMunicipality(
        Request $request,
        User $user,
        $municipalities
    ): Municipality {
        if ($municipalities->isEmpty()) {
            abort(403, 'No active municipality is available for your account.');
        }

        if (! $user->canAccessAllMunicipalities()) {
            return $municipalities->first();
        }

        $requestedId = $request->input('municipality_id');

        if ($requestedId === null || $requestedId === '') {
            return $municipalities->first();
        }

        if (! filter_var(
            $requestedId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        )) {
            throw ValidationException::withMessages([
                'municipality_id' => 'Please select an active municipality.',
            ]);
        }

        $municipality = $municipalities->firstWhere('id', (int) $requestedId);

        if (! $municipality) {
            throw ValidationException::withMessages([
                'municipality_id' => 'The selected municipality is unavailable.',
            ]);
        }

        return $municipality;
    }

}
