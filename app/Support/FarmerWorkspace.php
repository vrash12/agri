<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FarmerWorkspace
{
    public function __construct(private MunicipalityAccess $access)
    {
    }

    /**
     * The hierarchy narrows already authorized choices; it never grants access.
     * Only municipality_id opens records, including existing bookmarked URLs.
     *
     * @return array<string, mixed>
     */
    public function resolve(Request $request, User $user): array
    {
        $municipalities = $this->access->choices($user);
        if (! $user->canAccessAllMunicipalities()) {
            abort_unless($municipalities->first() instanceof Municipality, 403);

            return ['municipalities' => $municipalities, 'selectedMunicipality' => $municipalities->first()];
        }

        $input = $request->validate([
            'region_id' => ['nullable', 'string', 'max:30'],
            'province_id' => ['nullable', 'integer', 'min:1'],
            'municipality_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $provinceQuery = $this->access->scopeProvinces(Province::query()->active(), $user)
            ->whereIn('id', $municipalities->pluck('province_id')->unique())->orderBy('name');
        // A legacy workspace without configured regions remains reachable through
        // an explicit unassigned group, never an inferred geographic assignment.
        $hasRegions = Schema::hasTable('regions') && Schema::hasColumn('provinces', 'region_id');
        if ($hasRegions) {
            $provinceQuery->with('region:id,name');
        }
        $provinces = $provinceQuery->get($hasRegions ? ['id', 'name', 'region_id'] : ['id', 'name']);
        $regionKey = fn (Province $province): string => $province->region_id ? (string) $province->region_id : 'unassigned';
        $regions = $provinces->groupBy($regionKey)->map(function ($items, $key) use ($hasRegions): array {
            return [
                'id' => (string) $key,
                'name' => $hasRegions && $items->first()->region_id ? $items->first()->region?->name : 'Region not assigned',
                'provinces' => $items->count(),
            ];
        })->sortBy('name')->values();

        $selectedMunicipality = null;
        if (! empty($input['municipality_id'])) {
            $selectedMunicipality = $municipalities->firstWhere('id', (int) $input['municipality_id']);
            if (! $selectedMunicipality) {
                throw ValidationException::withMessages(['municipality_id' => 'The selected municipality is unavailable.']);
            }
        }
        $provinceId = $input['province_id'] ?? $selectedMunicipality?->province_id ?? ($user->requiresProvince() ? $user->province_id : null);
        $selectedProvince = $provinceId ? $provinces->firstWhere('id', (int) $provinceId) : null;
        if (($provinceId && ! $selectedProvince) || ($selectedMunicipality && $selectedMunicipality->province_id !== $selectedProvince?->id)) {
            throw ValidationException::withMessages(['province_id' => 'The selected province is unavailable for this workspace.']);
        }
        $selectedRegionId = (string) ($input['region_id'] ?? ($selectedProvince ? $regionKey($selectedProvince) : ($user->isRegionalHead() ? $user->region_id : '')));
        if (($selectedRegionId !== '' && ! $regions->contains('id', $selectedRegionId) && (! $regions->isEmpty() || filled($input['region_id'] ?? null))) || ($selectedProvince && $regionKey($selectedProvince) !== $selectedRegionId)) {
            throw ValidationException::withMessages(['region_id' => 'The selected region is unavailable for this workspace.']);
        }

        return [
            'workspaceRegions' => $regions,
            'workspaceProvinces' => $selectedRegionId === '' ? collect() : $provinces->filter(fn ($province) => $regionKey($province) === $selectedRegionId)->values(),
            'workspaceRegionId' => $selectedRegionId,
            'workspaceRegionName' => $regions->firstWhere('id', $selectedRegionId)['name'] ?? null,
            'workspaceProvince' => $selectedProvince,
            'municipalities' => $selectedProvince ? $municipalities->where('province_id', $selectedProvince->id)->values() : collect(),
            'selectedMunicipality' => $selectedMunicipality,
        ];
    }
}
