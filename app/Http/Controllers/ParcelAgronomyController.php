<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveParcelFieldAssessmentRequest;
use App\Models\FarmPlot;
use App\Models\ParcelFieldAssessment;
use App\Modules\Climate\Application\ClimateAutomaticMonitor;
use App\Modules\Climate\Application\ClimateEvidenceContext;
use App\Modules\Climate\Application\ClimateRiskDashboard;
use App\Modules\Climate\Application\ParcelFieldAssessments;
use App\Modules\Climate\Application\ParcelSatelliteHistory;
use App\Modules\Climate\Application\ParcelStressMonitor;
use App\Modules\Climate\Application\ParcelSuitability;
use App\Support\ConcurrentWrite;
use App\Support\LocalTime;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParcelAgronomyController extends Controller
{
    public function __construct(private ParcelFieldAssessments $fields) {}

    public function show(Request $request, FarmPlot $plot, ClimateRiskDashboard $dashboard, ClimateAutomaticMonitor $monitor,
        ParcelSatelliteHistory $satellites, ParcelSuitability $suitabilityEngine, ParcelStressMonitor $stressMonitor)
    {
        $this->authorize('view', $plot);
        abort_unless($plot->farmer, 404);
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:1990,'.(LocalTime::now()->year + 1)],
            'season' => ['nullable', Rule::in(['dry', 'wet'])],
        ]);
        $year = (int) ($data['year'] ?? LocalTime::now()->year);
        $season = $data['season'] ?? 'dry';
        $plot->load('farmer.municipality');
        $latest = $this->fields->latest($plot, $request->user(), $year, $season);
        $assessment = $latest ? $this->fields->present($latest, $plot) : null;
        $history = $this->fields->query($plot, $request->user())->orderByDesc('id')->paginate(10)->withQueryString()
            ->through(fn (ParcelFieldAssessment $row): array => $this->fields->present($row, $plot));
        $climateMonitoring = $monitor->summarize($dashboard->automatedEvidence($request->user(), $plot->farmer->municipality));
        $satelliteHistory = $satellites->forPlot($plot);
        $suitability = $suitabilityEngine->evaluate($assessment ?? []);
        $suitabilityProfiles = [];
        foreach (array_keys(ParcelFieldAssessment::CROPS) as $crop) {
            $suitabilityProfiles[$crop] = $suitabilityEngine->evaluate(array_replace($assessment ?? [], ['crop' => $crop]));
        }
        $stress = $stressMonitor->summarize($assessment, $climateMonitoring, $satelliteHistory);

        return view('farm_plots.agronomy', compact('plot', 'assessment', 'history', 'year', 'season', 'suitability', 'suitabilityProfiles', 'stress', 'climateMonitoring', 'satelliteHistory') + [
            'canEdit' => $request->user()->can('update', $plot),
            'recordVersion' => $latest ? ConcurrentWrite::version($latest) : 'new',
            'plotVersion' => ConcurrentWrite::version($plot), 'contextFingerprint' => ClimateEvidenceContext::plot($plot),
            'today' => LocalTime::now()->toDateString(), 'cropChoices' => ParcelFieldAssessment::CROPS,
            'stageChoices' => ParcelFieldAssessment::STAGES, 'waterChoices' => ParcelFieldAssessment::WATER,
            'conditionChoices' => ParcelFieldAssessment::CONDITIONS,
        ]);
    }

    public function store(SaveParcelFieldAssessmentRequest $request, FarmPlot $plot)
    {
        $record = $this->fields->save($plot, $request->user(), $request->validated());

        return redirect()->route('farm-plots.agronomy.show', ['plot' => $plot->id, 'year' => $record->crop_year, 'season' => $record->season])
            ->with('success', 'Field check saved. The parcel guidance now uses your latest observation.');
    }
}
