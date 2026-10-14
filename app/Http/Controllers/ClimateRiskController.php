<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClimateRiskAssessmentRequest;
use App\Models\ClimateRiskAssessment;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Services\WeatherForecastService;
use App\Support\BarangayBoundaryReferences;
use App\Support\ClimateRiskDashboard;
use App\Support\ClimateRiskRecords;
use App\Support\MunicipalityAccess;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClimateRiskController extends Controller
{
    public function __construct(private MunicipalityAccess $access, private ClimateRiskDashboard $dashboard, private BarangayBoundaryReferences $barangays) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', ClimateRiskAssessment::class);
        $choices = $this->access->choices($request->user());
        $selected = $this->selected($request, $choices);
        $filter = $request->validate(['risk' => ['nullable', Rule::in([...array_keys(ClimateRiskAssessment::LEVELS), 'unassessed'])]])['risk'] ?? null;
        $installed = $this->dashboard->installed();
        $rows = $installed ? $this->dashboard->overview($request->user(), $choices) : collect();
        $totals = $rows->countBy('level');
        $visible = $rows->filter(fn (array $row) => (! $selected || $row['id'] === $selected->id) && (! $filter || $row['level'] === $filter))->values();
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator($visible->forPage($page, 30)->values(), $visible->count(), 30, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $assessments = $installed && $selected ? $this->dashboard->assessments($request->user())->where('municipality_id', $selected->id)->orderBy('barangay')->paginate(15, ['*'], 'areas_page')->withQueryString() : null;
        $plots = $installed && $selected ? $this->dashboard->affectedPlots($request->user(), $selected->id)->orderBy('id')->paginate(15, $this->plotColumns(), 'plots_page')->withQueryString() : null;

        return view('climate_risk.index', compact('choices', 'selected', 'filter', 'installed', 'rows', 'totals', 'paginator', 'assessments', 'plots') + [
            'plotSummaries' => $plots?->getCollection()->map(fn (FarmPlot $plot) => $this->dashboard->plotSummary($plot))->keyBy('id') ?? collect(),
            'automatedEvidence' => $selected ? $this->dashboard->automatedEvidence($request->user(), $selected) : ['open_meteo' => null, 'chirps' => null, 'sentinel2' => null],
            'mapConfig' => ['mapUrl' => route('climate-risk.map', ['municipality_id' => $selected?->id, 'risk' => $filter]),
                'rainfallUrl' => $selected ? route('climate-risk.rainfall', ['municipality_id' => $selected->id]) : null,
                'areaUrl' => route('climate-risk.index'), 'mapsKey' => config('services.google_maps.key'), 'municipalityId' => $selected?->id],
        ]);
    }

    public function mapData(Request $request)
    {
        $this->authorize('viewAny', ClimateRiskAssessment::class);
        abort_unless($this->dashboard->installed(), 503, 'The climate registry needs its migration before the map can load.');
        $choices = $this->access->choices($request->user());
        $selected = $this->selected($request, $choices);
        $rows = $this->dashboard->overview($request->user(), $selected ? $choices->where('id', $selected->id) : $choices);
        $filter = $request->validate(['risk' => ['nullable', Rule::in([...array_keys(ClimateRiskAssessment::LEVELS), 'unassessed'])]])['risk'] ?? null;
        if ($filter) {
            $rows = $rows->where('level', $filter)->values();
        }
        $ids = $rows->pluck('id')->take(80)->all();
        // Bound source geometry in SQL before hydrating it; use reference points for oversized outlines.
        $boundaries = MunicipalityBoundary::query()->active()->whereIn('municipality_id', $ids)->orderByDesc('id')->get([
            'id', 'municipality_id', 'centroid_lat', 'centroid_lng', DB::raw('CASE WHEN LENGTH(geojson) <= 100000 THEN geojson ELSE NULL END as geojson'),
        ])->unique('municipality_id')->keyBy('municipality_id');
        $budget = 1000000;
        $features = $rows->take(80)->map(function (array $row) use ($boundaries, &$budget): array {
            $boundary = $boundaries->get($row['id']);
            $geometry = $boundary?->geojson;
            $size = $geometry ? strlen(json_encode($geometry)) : 0;
            if ($size > $budget) {
                $geometry = null;
            } else {
                $budget -= $size;
            }

            return ['type' => 'Feature', 'id' => $row['id'], 'geometry' => $geometry,
                'properties' => $row + ['center' => $boundary && is_numeric($boundary->centroid_lat) && is_numeric($boundary->centroid_lng)
                    ? ['lat' => $boundary->centroid_lat, 'lng' => $boundary->centroid_lng] : null]];
        });
        $barangays = $selected ? $this->barangays->forMunicipality($selected) : null;
        $areaKeys = array_map(fn (array $feature) => hash('sha256', mb_strtolower($feature['properties']['name'])), $barangays['features'] ?? []);
        $areaRows = $selected && $areaKeys ? $this->dashboard->assessments($request->user())->where('municipality_id', $selected->id)->whereIn('area_key', $areaKeys)->get(['barangay', 'risk_level', 'observed_on', 'valid_until']) : collect();
        $areaLevels = $areaRows->mapWithKeys(fn (ClimateRiskAssessment $row) => [mb_strtolower($row->barangay) => [
            'level' => $row->isCurrent() ? $row->risk_level : 'unassessed', 'observed_on' => $row->observed_on->toDateString(), 'review_on' => $row->valid_until->toDateString(),
        ]]);
        if ($barangays) {
            foreach ($barangays['features'] as &$feature) {
                $risk = $areaLevels->get(mb_strtolower($feature['properties']['name'])) ?? ['level' => 'unassessed', 'observed_on' => null, 'review_on' => null];
                $feature['properties'] += $risk + ['label' => ClimateRiskAssessment::LEVELS[$risk['level']] ?? 'Not assessed', 'color' => ClimateRiskDashboard::COLORS[$risk['level']]];
            }
            unset($feature);
        }
        $plots = $selected ? $this->dashboard->affectedPlots($request->user(), $selected->id)->orderBy('id')->limit(80)->get($this->plotColumns()) : collect();
        $affectedTotal = $selected ? $this->dashboard->affectedPlots($request->user(), $selected->id)->count() : 0;
        $parcels = $plots->take(80)->map(function (FarmPlot $plot) use (&$budget): array {
            $points = $plot->polygon_json;
            $size = is_array($points) ? strlen(json_encode($points)) : 0;
            $valid = is_array($points) && count($points) >= 3 && count($points) <= 10000 && $size <= $budget;
            if ($valid) {
                foreach ($points as $point) {
                    if (! is_array($point) || ! is_numeric($point['lat'] ?? null) || ! is_numeric($point['lng'] ?? null)
                        || abs((float) $point['lat']) > 90 || abs((float) $point['lng']) > 180) {
                        $valid = false;
                        break;
                    }
                }
            }
            if ($valid) {
                $budget -= $size;
            }

            return $this->dashboard->plotSummary($plot) + ['path' => $valid ? $points : null];
        });

        return response()->json(['type' => 'FeatureCollection', 'features' => $features->values(), 'total' => $rows->count(),
            'barangays' => $barangays, 'parcels' => $parcels->values(), 'affected_total' => $affectedTotal]);
    }

    public function rainfall(Request $request, WeatherForecastService $weather)
    {
        $this->authorize('viewAny', ClimateRiskAssessment::class);
        $selected = $this->selected($request, $this->access->choices($request->user()));
        abort_unless($selected, 422, 'Choose one municipality to load its rainfall forecast.');
        $boundary = $selected->activeBoundary()->first(['centroid_lat', 'centroid_lng']);
        if (! $boundary || ! is_numeric($boundary->centroid_lat) || ! is_numeric($boundary->centroid_lng)) {
            return response()->json(['available' => false, 'status_message' => 'This municipality needs an active boundary reference point before its forecast can load.']);
        }

        return response()->json($weather->forMunicipalityAt($selected, ['latitude' => $boundary->centroid_lat, 'longitude' => $boundary->centroid_lng]));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ClimateRiskAssessment::class);

        return $this->form($request, new ClimateRiskAssessment);
    }

    public function edit(Request $request, ClimateRiskAssessment $assessment)
    {
        $this->authorize('update', $assessment);

        return $this->form($request, $assessment);
    }

    public function store(SaveClimateRiskAssessmentRequest $request, ClimateRiskRecords $records)
    {
        abort_unless($this->dashboard->installed(), 503);
        $record = $records->save($request->user(), $request->validated());

        return redirect()->route('climate-risk.index', ['municipality_id' => $record->municipality_id])->with('success', 'Climate assessment saved. Its date, source and reported parcels now appear on the dashboard.');
    }

    public function update(SaveClimateRiskAssessmentRequest $request, ClimateRiskAssessment $assessment, ClimateRiskRecords $records)
    {
        $record = $records->save($request->user(), $request->validated(), $assessment);

        return redirect()->route('climate-risk.index', ['municipality_id' => $record->municipality_id])->with('success', 'Climate assessment updated.');
    }

    public function destroy(Request $request, ClimateRiskAssessment $assessment, ClimateRiskRecords $records)
    {
        $this->authorize('delete', $assessment);
        $data = $request->validate(['_record_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $records->withdraw($request->user(), $assessment, $data['_record_version']);

        return redirect()->route('climate-risk.index', ['municipality_id' => $assessment->municipality_id])->with('success', 'Assessment withdrawn. The dashboard will use the remaining current assessments.');
    }

    public function plotLookup(Request $request)
    {
        $this->authorize('create', ClimateRiskAssessment::class);
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $selected = $this->selected($request, $this->access->choices($request->user()));
        abort_unless($selected, 422, 'Choose a municipality first.');
        $q = trim((string) $request->query('q'));
        $plots = FarmPlot::query()->whereHas('farmer', fn ($query) => $query->where('municipality_id', $selected->id))
            ->where(fn ($query) => $query->where('name', 'like', '%'.str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $q).'%')->when(ctype_digit($q), fn ($query) => $query->orWhere('id', (int) $q)))
            ->orderBy('name')->limit(21)->get(['id', 'name']);

        return response()->json(['plots' => $plots->take(20)->values(), 'truncated' => $plots->count() > 20]);
    }

    private function form(Request $request, ClimateRiskAssessment $assessment)
    {
        abort_unless($this->dashboard->installed(), 503, 'Install the climate registry migration before entering assessments.');
        $choices = $this->access->choices($request->user());
        $selected = $assessment->exists ? $choices->firstWhere('id', $assessment->municipality_id) : $this->selected($request, $choices);
        $plots = $assessment->exists ? $assessment->plots()->whereHas('farmer', fn ($query) => $query->where('municipality_id', $assessment->municipality_id))->get(['farm_plots.id', 'name']) : collect();
        $oldIds = $request->session()->getOldInput('plot_ids');
        if (is_array($oldIds) && $selected) {
            $plots = FarmPlot::query()->whereIn('id', array_slice($oldIds, 0, 100))->whereHas('farmer', fn ($query) => $query->where('municipality_id', $selected->id))->get(['id', 'name']);
        }

        return view('climate_risk.form', compact('assessment', 'choices', 'selected', 'plots') + [
            'barangayChoices' => $selected ? $this->barangays->forMunicipality($selected)['features'] : [],
            'formConfig' => ['lookupUrl' => route('climate-risk.parcels', ['municipality_id' => $selected?->id])],
        ]);
    }

    private function selected(Request $request, $choices): ?Municipality
    {
        $data = $request->validate(['municipality_id' => ['nullable', 'integer', 'min:1']]);
        $id = $data['municipality_id'] ?? null;
        if (! $request->user()->canAccessAllMunicipalities()) {
            if ($id && (int) $id !== $request->user()->municipality_id) {
                abort(404);
            }

            return $choices->firstWhere('id', $request->user()->municipality_id);
        }
        if ($id) {
            return $choices->firstWhere('id', (int) $id) ?? abort(404);
        }

        return null;
    }

    /** Keep large legacy geometry out of dashboard memory; those parcels still remain listed. */
    private function plotColumns(): array
    {
        return ['id', 'farmer_id', 'name', DB::raw('CASE WHEN LENGTH(polygon_json) <= 500000 THEN polygon_json ELSE NULL END as polygon_json')];
    }
}
