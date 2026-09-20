<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParcelCropSeasonRequest;
use App\Models\FarmPlot;
use App\Models\ParcelCropSeason;
use App\Support\ConcurrentWrite;
use App\Support\LocalTime;
use App\Support\ParcelCropSeasons;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParcelCropSeasonController extends Controller
{
    public function __construct(private ParcelCropSeasons $crops)
    {
    }

    public function edit(Request $request, FarmPlot $plot)
    {
        $this->authorize('view', $plot);
        abort_unless($plot->farmer, 404);
        $data = $request->validate($this->periodRules(false));
        $year = (int) ($data['year'] ?? LocalTime::now()->year);
        $season = $data['season'] ?? 'dry';
        $plot->load('farmer.municipality');
        $query = ParcelCropSeason::query()->where('farm_plot_id', $plot->id)
            ->where('municipality_id', $plot->farmer->municipality_id);
        $cropRecord = (clone $query)->where('crop_year', $year)->where('season', $season)->first();
        $recordVersion = $cropRecord ? ConcurrentWrite::version($cropRecord) : 'new';
        $history = $query->orderByDesc('crop_year')->orderBy('season')->paginate(10)->withQueryString();

        return view('farm_plots.seasonal_crops', compact('plot', 'year', 'season', 'cropRecord', 'recordVersion', 'history') + [
            'cropChoices' => ParcelCropSeason::CROPS, 'seasonChoices' => ParcelCropSeason::SEASONS,
        ]);
    }

    public function store(StoreParcelCropSeasonRequest $request, FarmPlot $plot)
    {
        $record = $this->crops->save($plot, $request->user(), $request->validated());

        return redirect()->route('farm-plots.seasonal-crops.edit', [
            'plot' => $plot->id, 'year' => $record->crop_year, 'season' => $record->season,
        ])->with('success', 'Seasonal crop saved. Open Crops by season on the parcel map to see the classification.');
    }

    public function layer(Request $request)
    {
        $this->authorize('viewAny', FarmPlot::class);
        $data = $request->validate($this->periodRules(true) + [
            'plot_ids' => ['required', 'array', 'max:200'],
            'plot_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return response()->json($this->crops->layer($request->user(), $data['plot_ids'], (int) $data['year'], $data['season']));
    }

    private function periodRules(bool $required): array
    {
        return [
            'year' => [$required ? 'required' : 'nullable', 'integer', 'between:1990,'.(LocalTime::now()->year + 1)],
            'season' => [$required ? 'required' : 'nullable', Rule::in(array_keys(ParcelCropSeason::SEASONS))],
        ];
    }
}
