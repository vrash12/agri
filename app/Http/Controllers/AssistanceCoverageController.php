<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssistanceCoverageRequest;
use App\Models\RiceSeedDistribution;
use App\Support\AssistanceCoverage;
use Illuminate\Http\Request;

class AssistanceCoverageController extends Controller
{
    public function __construct(private AssistanceCoverage $coverage)
    {
    }

    public function index(AssistanceCoverageRequest $request)
    {
        $data = $request->validated();
        $provinces = $this->coverage->provinces($request->user());
        $provinceId = (int) ($data['province_id'] ?? $provinces->first()?->id ?? 0);
        abort_if($provinceId && ! $provinces->contains('id', $provinceId), 404);
        $query = $this->coverage->municipalities($request->user(), $provinceId);
        $municipalities = (clone $query)->orderBy('name')->limit(AssistanceCoverage::MUNICIPALITY_LIMIT + 1)->get(['id', 'name']);
        $truncated = $municipalities->count() > AssistanceCoverage::MUNICIPALITY_LIMIT;
        $municipalities = $municipalities->take(AssistanceCoverage::MUNICIPALITY_LIMIT);
        if (! empty($data['municipality_id'])) {
            $selected = (clone $query)->whereKey($data['municipality_id'])->firstOrFail(['id', 'name']);
            $reportMunicipalities = collect([$selected]);
            $truncated = false;
        } else {
            $reportMunicipalities = $municipalities;
        }
        $filters = [
            'province_id' => $provinceId, 'municipality_id' => $data['municipality_id'] ?? null,
            'category' => $data['category'] ?? '', 'reference' => $data['reference'] ?? '',
            'year' => isset($data['year']) ? (int) $data['year'] : null,
            'season' => $data['season'] ?? 'all',
        ];

        return view('assistance_coverage.index', $this->coverage->report($request->user(), $reportMunicipalities, $filters) + [
            'filters' => $filters, 'provinces' => $provinces, 'municipalities' => $municipalities,
            'provinceName' => $provinces->firstWhere('id', $provinceId)?->name ?? 'No active province',
            'truncated' => $truncated, 'categoryOptions' => RiceSeedDistribution::INPUT_CATEGORY_LABELS,
            'googleMapsKey' => (string) config('services.google_maps.key', ''),
        ]);
    }

    public function boundaries(Request $request)
    {
        $this->authorize('viewAny', RiceSeedDistribution::class);
        $data = $request->validate([
            'municipality_ids' => ['required', 'array', 'min:1', 'max:10'],
            'municipality_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return response()->json($this->coverage->boundaries($request->user(), $data['municipality_ids']));
    }
}
