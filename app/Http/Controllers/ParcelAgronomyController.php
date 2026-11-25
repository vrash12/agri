<?php

namespace App\Http\Controllers;

use App\Exceptions\AssistantUnavailable;
use App\Http\Requests\SaveParcelFieldAssessmentRequest;
use App\Models\FarmPlot;
use App\Models\ParcelFieldAssessment;
use App\Modules\Assistant\Application\ParcelCropAdvice;
use App\Modules\Climate\Application\ClimateEvidenceContext;
use App\Modules\Climate\Application\ParcelBriefing;
use App\Modules\Climate\Application\ParcelFieldAssessments;
use App\Modules\Climate\Application\ParcelSuitability;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParcelAgronomyController extends Controller
{
    public function __construct(private ParcelFieldAssessments $fields, private ParcelBriefing $briefings) {}

    public function show(Request $request, FarmPlot $plot, ParcelSuitability $suitabilityEngine, ParcelCropAdvice $advice)
    {
        $this->authorize('view', $plot);
        abort_unless($plot->farmer, 404);
        [$year, $season] = $this->period($request);
        $evidence = $this->briefings->evidence($plot, $request->user(), $year, $season);
        $latest = $evidence['latest'];
        $assessment = $evidence['assessment'];
        $history = $this->fields->query($plot, $request->user())->orderByDesc('id')->paginate(10)->withQueryString()
            ->through(fn (ParcelFieldAssessment $row): array => $this->fields->present($row, $plot));
        $climateMonitoring = $evidence['climate'];
        $satelliteHistory = $evidence['satellite'];
        $suitability = $evidence['suitability'];
        $suitabilityProfiles = [];
        foreach (array_keys(ParcelFieldAssessment::CROPS) as $crop) {
            $suitabilityProfiles[$crop] = $suitabilityEngine->evaluate(array_replace($assessment ?? [], ['crop' => $crop]));
        }
        $stress = $evidence['stress'];
        $briefing = $this->briefings->compose($plot, $request->user(), $year, $season, $evidence);

        return view('farm_plots.agronomy', compact('plot', 'assessment', 'history', 'year', 'season', 'suitability', 'suitabilityProfiles', 'stress', 'climateMonitoring', 'satelliteHistory', 'briefing') + [
            'canEdit' => $request->user()->can('update', $plot),
            'adviceAvailable' => $advice->available($request->user()),
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

    /** Explicit, read-only provider request. Categories are rebuilt server-side from saved evidence. */
    public function advice(Request $request, FarmPlot $plot, ParcelCropAdvice $advice): JsonResponse
    {
        $this->authorize('view', $plot);
        abort_unless($plot->farmer, 404);
        [$year, $season] = $this->period($request);
        $started = hrtime(true);
        try {
            $briefing = $this->briefings->compose($plot, $request->user(), $year, $season,
                $this->briefings->evidence($plot, $request->user(), $year, $season));
            $result = $advice->advise($request->user(), $briefing['categories']);
        } catch (AssistantUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status)->header('Cache-Control', 'private, no-store');
        }
        AuditTrail::record('viewed', 'Office Assistant', 'Requested general AI crop advice for a parcel.', [
            'auditable' => $plot, 'municipality_id' => (int) $plot->farmer->municipality_id,
            'metadata' => ['task' => 'crop_advice', 'delivery' => $result['delivery'],
                'provider_input_count' => $result['input_tokens'], 'provider_output_count' => $result['output_tokens'],
                'retrieved_passages' => $result['retrieved_passages'], 'cited_passages' => count($result['sources']),
                'latency_ms' => (int) round((hrtime(true) - $started) / 1000000)],
        ]);

        return response()->json(['answer' => $result['answer'], 'sources' => $result['sources'], 'delivery' => $result['delivery']])
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return array{0: int, 1: string} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:1990,'.(LocalTime::now()->year + 1)],
            'season' => ['nullable', Rule::in(['dry', 'wet'])],
        ]);

        return [(int) ($data['year'] ?? LocalTime::now()->year), $data['season'] ?? 'dry'];
    }
}
