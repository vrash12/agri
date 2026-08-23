<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\Municipality;
use App\Models\User;
use App\Services\WeatherForecastService;
use App\Support\MunicipalityAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WeatherAdvisoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(
        Request $request,
        MunicipalityAccess $municipalityAccess,
        WeatherForecastService $weather
    ): View {
        /** @var User $user */
        $user = $request->user();
        $municipalities = $municipalityAccess->choices($user);
        $municipality = $this->selectedMunicipality(
            $request,
            $user,
            $municipalities
        );
        $forecast = $weather->forMunicipality($municipality);
        [$affectedFarmers, $outreachSummary] = $this->outreachQueue(
            $municipality,
            $forecast
        );

        return view('weather.index', [
            'municipalities' => $municipalities,
            'selectedMunicipality' => $municipality,
            'forecast' => $forecast,
            'affectedFarmers' => $affectedFarmers,
            'outreachSummary' => $outreachSummary,
            'pagasaLinks' => (array) config('weather.pagasa', []),
        ]);
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

        return redirect()
            ->route('weather.index', ['municipality_id' => $municipality->id])
            ->with(
                $forecast['available'] ? 'success' : 'error',
                $forecast['available']
                    ? 'Weather forecast refreshed.'
                    : (string) $forecast['status_message']
            );
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

    /**
     * Build a municipality-scoped follow-up queue when the forecast crosses a
     * moderate or high operational threshold. This does not send messages.
     *
     * @param  array<string, mixed>  $forecast
     * @return array{0: \Illuminate\Support\Collection, 1: array<string, mixed>}
     */
    private function outreachQueue(
        Municipality $municipality,
        array $forecast
    ): array {
        $riskAdvisories = collect($forecast['advisories'] ?? [])
            ->filter(fn (array $advisory) => in_array(
                $advisory['severity'] ?? null,
                ['high', 'moderate'],
                true
            ));

        if ($riskAdvisories->isEmpty()) {
            return [collect(), [
                'active' => false,
                'total_mapped_farmers' => 0,
                'contactable_farmers' => 0,
                'missing_contact' => 0,
                'risk_categories' => [],
            ]];
        }

        $baseQuery = Farmer::query()
            ->where('municipality_id', $municipality->id)
            ->whereHas('farmPlots');
        $totalMappedFarmers = (clone $baseQuery)->count();
        $contactableFarmers = (clone $baseQuery)
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->count();
        $affectedFarmers = (clone $baseQuery)
            ->select([
                'farmers.id',
                'farmers.municipality_id',
                'farmers.first_name',
                'farmers.middle_name',
                'farmers.last_name',
                'farmers.ext_name',
                'farmers.contact_number',
                'farmers.farm_location',
                'farmers.ffrs',
            ])
            ->withCount('farmPlots')
            ->withSum('farmPlots as mapped_area_ha', 'area_ha')
            ->orderByRaw(
                "CASE WHEN contact_number IS NULL OR contact_number = '' THEN 1 ELSE 0 END"
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(12)
            ->get();

        return [$affectedFarmers, [
            'active' => true,
            'total_mapped_farmers' => $totalMappedFarmers,
            'contactable_farmers' => $contactableFarmers,
            'missing_contact' => max($totalMappedFarmers - $contactableFarmers, 0),
            'risk_categories' => $riskAdvisories
                ->pluck('category')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]];
    }
}
