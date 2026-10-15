<?php

namespace App\Modules\Climate\Application;

use App\Models\ClimateRiskAssessment;
use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\User;
use App\Modules\Land\Application\BarangayBoundaryReferences;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ClimateRiskRecords
{
    public function __construct(private MunicipalityAccess $access, private ConcurrentWrite $writes, private BarangayBoundaryReferences $barangays) {}

    public function save(User $user, array $data, ?ClimateRiskAssessment $assessment = null): ClimateRiskAssessment
    {
        Gate::forUser($user)->authorize($assessment ? 'update' : 'create', $assessment ?? ClimateRiskAssessment::class);
        $municipalityId = $this->access->resolveForWrite($user, $data['municipality_id']);
        $barangay = preg_replace('/\s+/u', ' ', trim((string) ($data['barangay'] ?? '')));
        $areaKey = $barangay === '' ? 'municipality' : hash('sha256', mb_strtolower($barangay));
        if ($assessment && ($assessment->municipality_id !== $municipalityId || $assessment->area_key !== $areaKey)) {
            throw ValidationException::withMessages(['barangay' => 'An assessment stays with its original area. Create a separate assessment for another location.']);
        }

        return $this->writes->transaction(function () use ($user, $data, $assessment, $municipalityId, $barangay, $areaKey): ClimateRiskAssessment {
            // All writers for an area share this row lock, including first creation.
            Municipality::query()->whereKey($municipalityId)->lockForUpdate()->firstOrFail();
            $this->access->resolveForWrite($user, $municipalityId);
            $reference = $this->barangays->forMunicipality(Municipality::findOrFail($municipalityId));
            if ($barangay && $reference['available'] && ! collect($reference['features'])->contains(fn (array $feature) => mb_strtolower($feature['properties']['name']) === mb_strtolower($barangay))) {
                throw ValidationException::withMessages(['barangay' => 'Choose a barangay name from this municipality’s saved reference list.']);
            }
            $plotIds = array_values(array_unique(array_map('intval', $data['plot_ids'] ?? [])));
            $farmerIds = FarmPlot::query()->whereIn('id', $plotIds)->orderBy('farmer_id')->pluck('farmer_id')->unique()->all();
            Farmer::query()->whereIn('id', $farmerIds)->orderBy('id')->lockForUpdate()->get(['id', 'municipality_id']);
            $plots = FarmPlot::query()->with('farmer:id,municipality_id')->whereIn('id', $plotIds)->orderBy('id')->lockForUpdate()->get();
            if ($plots->count() !== count($plotIds) || $plots->contains(fn (FarmPlot $plot) => $plot->farmer?->municipality_id !== $municipalityId)) {
                throw ValidationException::withMessages(['plot_ids' => 'Every affected parcel must belong to the selected municipality. Reload the parcel choices and try again.']);
            }
            foreach ($plots as $plot) {
                Gate::forUser($user)->authorize('view', $plot);
            }
            $save = function (ClimateRiskAssessment $record) use ($user, $data, $plotIds): ClimateRiskAssessment {
                Gate::forUser($user)->authorize($record->exists ? 'update' : 'create', $record->exists ? $record : ClimateRiskAssessment::class);
                $record->fill($data);
                $record->forceFill(['updated_by' => $user->id]);
                $record->save();
                $record->plots()->sync($plotIds);
                AuditTrail::record('saved', 'El Niño readiness', 'Saved a dated office climate assessment.', [
                    'actor' => $user, 'auditable' => $record, 'municipality_id' => $record->municipality_id,
                    'metadata' => ['risk_level' => $record->risk_level, 'observed_on' => $record->observed_on->toDateString(), 'affected_parcel_count' => count($plotIds)],
                ]);

                return $record;
            };
            if ($assessment) {
                return $this->writes->execute($assessment, $data['_record_version'] ?? null, $save);
            }
            if (ClimateRiskAssessment::query()->where('municipality_id', $municipalityId)->where('area_key', $areaKey)->exists()) {
                throw ValidationException::withMessages(['barangay' => 'This area already has an assessment. Open its existing assessment to update it.']);
            }
            $record = new ClimateRiskAssessment;
            $record->forceFill(['municipality_id' => $municipalityId, 'area_key' => $areaKey, 'barangay' => $barangay ?: null, 'created_by' => $user->id]);

            return $save($record);
        });
    }

    public function withdraw(User $user, ClimateRiskAssessment $assessment, string $version): void
    {
        Gate::forUser($user)->authorize('delete', $assessment);
        $this->writes->transaction(function () use ($user, $assessment, $version): void {
            Municipality::query()->whereKey($assessment->municipality_id)->lockForUpdate()->firstOrFail();
            $this->writes->execute($assessment, $version, function (ClimateRiskAssessment $current) use ($user): void {
                Gate::forUser($user)->authorize('delete', $current);
                AuditTrail::record('withdrawn', 'El Niño readiness', 'Withdrew an office climate assessment.', ['actor' => $user, 'auditable' => $current, 'municipality_id' => $current->municipality_id]);
                $current->delete();
            });
        });
    }
}
