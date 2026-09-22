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
        // province_id remains accepted for old bookmarks. New workspace forms only
        // submit region_id and municipality_id, so a legacy province never narrows
        // the municipality list to one province when a region is selected.
        $legacyProvince = null;
        if (! empty($input['province_id'])) {
            $legacyProvince = $provinces->firstWhere('id', (int) $input['province_id']);
            if (! $legacyProvince) {
                throw ValidationException::withMessages(['province_id' => 'The selected province is unavailable for this workspace.']);
            }
        }

        $selectedProvince = $legacyProvince;
        $fixedRegionId = null;
        if ($user->isRegionalHead()) {
            $fixedRegionId = (string) $user->region_id;
        } elseif ($user->requiresProvince() && ! $user->isSystemOwner()) {
            $selectedProvince = $provinces->firstWhere('id', (int) $user->province_id);
            if (! $selectedProvince) {
                throw ValidationException::withMessages(['province_id' => 'Your assigned province is unavailable for this workspace.']);
            }
            $fixedRegionId = $regionKey($selectedProvince);
            if ($legacyProvince && $legacyProvince->id !== $selectedProvince->id) {
                throw ValidationException::withMessages(['province_id' => 'The selected province is unavailable for this workspace.']);
            }
        }

        $selectedRegionId = (string) ($fixedRegionId ?? ($input['region_id'] ?? ($legacyProvince ? $regionKey($legacyProvince) : '')));
        if ($fixedRegionId !== null && filled($input['region_id'] ?? null) && (string) $input['region_id'] !== $fixedRegionId) {
            throw ValidationException::withMessages(['region_id' => 'The selected region is unavailable for this workspace.']);
        }
        if ($selectedRegionId !== '' && ! $regions->contains('id', $selectedRegionId)) {
            throw ValidationException::withMessages(['region_id' => 'The selected region is unavailable for this workspace.']);
        }
        if ($legacyProvince && $regionKey($legacyProvince) !== $selectedRegionId) {
            throw ValidationException::withMessages(['region_id' => 'The selected region is unavailable for this workspace.']);
        }

        if ($selectedMunicipality) {
            $municipalityProvince = $provinces->firstWhere('id', (int) $selectedMunicipality->province_id);
            $municipalityRegionId = $municipalityProvince ? $regionKey($municipalityProvince) : null;
            if ($municipalityRegionId === null || ($selectedRegionId !== '' && $municipalityRegionId !== $selectedRegionId)) {
                throw ValidationException::withMessages(['municipality_id' => 'The selected municipality is unavailable for this region.']);
            }
            // A supplied province belongs to an old URL. Continue to enforce its
            // parent relationship when present, while new region-only links may
            // select any municipality in that region.
            if ($legacyProvince && $selectedMunicipality->province_id !== $legacyProvince->id) {
                throw ValidationException::withMessages(['province_id' => 'The selected municipality is unavailable for this province.']);
            }
            $selectedRegionId = $municipalityRegionId;
        }

        $regionProvinceIds = $selectedRegionId === ''
            ? []
            : $provinces->filter(fn (Province $province): bool => $regionKey($province) === $selectedRegionId)
                ->pluck('id')->all();
        $regionMunicipalities = $selectedRegionId === ''
            ? collect()
            : $municipalities->whereIn('province_id', $regionProvinceIds)->values();

        return [
            'workspaceRegions' => $regions,
            'workspaceProvinces' => $selectedRegionId === '' ? collect() : $provinces->filter(fn ($province) => $regionKey($province) === $selectedRegionId)->values(),
            'workspaceRegionId' => $selectedRegionId,
            'workspaceRegionName' => $regions->firstWhere('id', $selectedRegionId)['name'] ?? null,
            'workspaceProvince' => $selectedProvince,
            'municipalities' => $regionMunicipalities,
            'selectedMunicipality' => $selectedMunicipality,
        ];
    }
}
