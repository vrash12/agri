<?php

namespace App\Modules\Agriculture\Application;

use App\Models\Farmer;
use App\Models\HarvestRecord;
use App\Models\User;
use App\Modules\Land\Contracts\HarvestSectionGuard;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/** Direct harvest writes; projected release harvests retain their separate workflow. */
final class RecordHarvest
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $writes,
        private HarvestSectionGuard $sections,
    ) {}

    public function create(User $user, array $validated): HarvestRecord
    {
        Gate::forUser($user)->authorize('create', HarvestRecord::class);
        $municipalityId = $this->resolveMunicipality($user, $validated);

        return $this->writes->transaction(function () use ($user, $validated, $municipalityId): HarvestRecord {
            $this->sections->assertMatches($validated, $user);

            return HarvestRecord::create($this->payload($validated, $municipalityId, $user));
        });
    }

    public function update(User $user, HarvestRecord $record, array $validated, ?string $version): void
    {
        Gate::forUser($user)->authorize('update', $record);
        $municipalityId = $this->resolveMunicipality($user, $validated, $record);
        $this->writes->execute($record, $version, function (HarvestRecord $current) use ($user, $validated, $municipalityId): void {
            $this->sections->assertMatches($validated, $user);
            $current->update($this->payload($validated, $municipalityId, $user));
        });
    }

    private function resolveMunicipality(
        User $user,
        array $validated,
        ?HarvestRecord $existing = null
    ): int {
        if (! empty($validated['farmer_id'])) {
            $farmer = $this->municipalityAccess
                ->scope(Farmer::query(), $user)
                ->findOrFail($validated['farmer_id']);

            return (int) $farmer->municipality_id;
        }

        return $this->municipalityAccess->resolveForWrite(
            $user,
            $validated['municipality_id'] ?? $existing?->municipality_id
        );
    }

    private function payload(array $validated, int $municipalityId, User $user): array
    {
        $payload = [
            'municipality_id' => $municipalityId,
            'farmer_id' => $validated['farmer_id'] ?? null,
            'farm_plot_id' => $validated['farm_plot_id'] ?? null,
            'commodity' => $validated['commodity'],
            'variety' => $validated['variety'] ?? null,
            'season' => $validated['season'] ?? null,
            'harvest_year' => (int) $validated['harvest_year'],
            'date_harvested' => $validated['date_harvested'] ?? null,
            'area_harvested_ha' => $validated['area_harvested_ha'] ?? null,
            'quantity' => $validated['quantity'],
            'quantity_unit' => $validated['quantity_unit'],
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $user->id,
        ];

        if (Schema::hasColumn('harvest_records', 'parcel_crop_season_id')) {
            $payload['parcel_crop_season_id'] = $validated['parcel_crop_season_id'] ?? null;
        }
        if (Schema::hasColumn('harvest_records', 'planted_area_key')) {
            $payload['planted_area_key'] = $validated['planted_area_key'] ?? null;
        }

        return $payload;
    }
}
