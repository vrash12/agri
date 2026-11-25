<?php

namespace App\Http\Controllers;

use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Modules\Climate\Application\ParcelVisitPriority;
use App\Modules\Farmers\Application\FarmerWorkspace;
use App\Modules\Land\Application\MapQuestionAnswers;
use App\Modules\Land\Application\MapQuestionInterpreter;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only “Ask the map” and field-visit priority endpoints.
 *
 * The map workspace filters (region/province/municipality) are resolved by the
 * same FarmerWorkspace used for the parcel request, so answers can only narrow
 * the current map view. Nothing here writes, audits record text or calls a provider.
 */
class ParcelMapQuestionController extends Controller
{
    public function ask(Request $request, FarmerWorkspace $workspace, MapQuestionAnswers $answers): JsonResponse
    {
        $this->authorize('viewAny', FarmPlot::class);
        $data = $request->validate($this->periodRules() + [
            'question' => ['required', 'string', 'max:'.MapQuestionInterpreter::MAX_LENGTH],
        ]);
        $scope = $workspace->resolve($request, $request->user());

        return $this->private(response()->json($answers->answer(
            $request->user(), $scope['workspaceMunicipalityIds'], $data['question'],
            ['year' => (int) $data['year'], 'season' => $data['season']], LocalTime::now()->year, $scope['workspaceParameters'],
        )));
    }

    public function matches(Request $request, FarmerWorkspace $workspace, MapQuestionAnswers $answers): JsonResponse
    {
        $this->authorize('viewAny', FarmPlot::class);
        $data = $request->validate($this->periodRules() + $this->batchRules() + [
            'filter' => ['required', Rule::in(['crop', 'no_harvest'])],
            'crop' => ['nullable', Rule::in(array_keys(ParcelCropSeason::CROPS))],
            'location' => ['nullable', 'string', 'max:80'],
        ]);
        $scope = $workspace->resolve($request, $request->user());
        $result = $answers->matches($request->user(), $scope['workspaceMunicipalityIds'], $data['plot_ids'], $data['filter'],
            $data['crop'] ?? null, (int) $data['year'], $data['season'], $data['location'] ?? null);
        if (isset($result['clarify'])) {
            return response()->json(['message' => $result['clarify']], 422);
        }

        return $this->private(response()->json($result));
    }

    public function priority(Request $request, FarmerWorkspace $workspace, ParcelVisitPriority $priority): JsonResponse
    {
        $this->authorize('viewAny', FarmPlot::class);
        $data = $request->validate($this->periodRules() + $this->batchRules());
        $scope = $workspace->resolve($request, $request->user());

        return $this->private(response()->json($priority->evaluate($request->user(), $scope['workspaceMunicipalityIds'],
            $data['plot_ids'], (int) $data['year'], $data['season'])));
    }

    private function periodRules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:1990,'.(LocalTime::now()->year + 1)],
            'season' => ['required', Rule::in(array_keys(ParcelCropSeason::SEASONS))],
        ];
    }

    private function batchRules(): array
    {
        return [
            'plot_ids' => ['required', 'array', 'max:'.MapQuestionAnswers::BATCH],
            'plot_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, private');
    }
}
