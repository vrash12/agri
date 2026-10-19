<?php

namespace App\Modules\Climate\Application;

use App\Models\Farmer;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\ParcelFieldAssessment;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Append-only field observations; old ownership or boundary context never becomes current evidence. */
final class ParcelFieldAssessments
{
    public function __construct(private ConcurrentWrite $writes, private MunicipalityAccess $access) {}

    public function query(FarmPlot $plot, User $user): Builder
    {
        Gate::forUser($user)->authorize('view', $plot);

        return $this->access->scope(ParcelFieldAssessment::query(), $user)
            ->where('farm_plot_id', $plot->id)->where('farmer_id', $plot->farmer_id)
            ->where('municipality_id', $plot->farmer->municipality_id);
    }

    public function latest(FarmPlot $plot, User $user, int $year, string $season): ?ParcelFieldAssessment
    {
        return $this->query($plot, $user)->where('crop_year', $year)->where('season', $season)->orderByDesc('id')->first();
    }

    public function present(ParcelFieldAssessment $record, FarmPlot $plot): array
    {
        return Arr::only($record->evidence ?? [], ParcelFieldAssessment::EVIDENCE_FIELDS) + [
            'id' => $record->id, 'crop' => $record->crop, 'crop_year' => $record->crop_year, 'season' => $record->season,
            'observed_on' => $record->observed_on->toDateString(), 'created_at' => $record->created_at?->toIso8601String(),
            'context_current' => $record->farmer_id === (int) $plot->farmer_id
                && $record->municipality_id === (int) $plot->farmer->municipality_id
                && hash_equals(ClimateEvidenceContext::plot($plot), $record->context_fingerprint),
        ];
    }

    public function save(FarmPlot $plot, User $user, array $data): ParcelFieldAssessment
    {
        Gate::forUser($user)->authorize('update', $plot);
        $plot->loadMissing('farmer');
        $farmerId = (int) $plot->farmer_id;
        $municipalityId = (int) $plot->farmer->municipality_id;

        return $this->writes->transaction(function () use ($plot, $user, $data, $farmerId, $municipalityId): ParcelFieldAssessment {
            // Match office writes' scope -> farmer -> parcel lock order, including simultaneous first entries.
            Municipality::query()->whereKey($municipalityId)->lockForUpdate()->firstOrFail();
            $farmer = Farmer::query()->whereKey($farmerId)->lockForUpdate()->firstOrFail();
            $current = FarmPlot::query()->whereKey($plot->id)->lockForUpdate()->firstOrFail();
            if ((int) $current->farmer_id !== $farmerId || (int) $farmer->municipality_id !== $municipalityId) {
                throw ValidationException::withMessages(['_plot_version' => 'This parcel moved to another owner or office. Reopen it before recording a field check.']);
            }
            $current->setRelation('farmer', $farmer);
            $actor = $user->fresh();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('update', $current);
            $this->access->resolveForWrite($actor, $municipalityId);
            if (! hash_equals(ConcurrentWrite::version($current), (string) ($data['_plot_version'] ?? ''))
                || ! hash_equals(ClimateEvidenceContext::plot($current), (string) ($data['_context_fingerprint'] ?? ''))) {
                throw ValidationException::withMessages(['_plot_version' => 'The parcel changed after you opened it. Reload and review the latest boundary before saving.']);
            }

            $latest = $this->latest($current, $actor, (int) $data['crop_year'], $data['season']);
            $version = $latest ? ConcurrentWrite::version($latest) : 'new';
            if (! hash_equals($version, (string) ($data['_record_version'] ?? ''))) {
                throw ValidationException::withMessages(['_record_version' => 'Another field check was saved for this season. Reload it before adding your next observation.']);
            }
            if ($latest && $data['observed_on'] < $latest->observed_on->toDateString()) {
                throw ValidationException::withMessages(['observed_on' => 'Use the latest field-check date or a later date so an older visit cannot replace current evidence.']);
            }

            $evidence = Arr::only($data, ParcelFieldAssessment::EVIDENCE_FIELDS);
            foreach (['soil_ph', 'slope_percent', 'growing_temperature_c', 'growing_rainfall_mm'] as $field) {
                $evidence[$field] = isset($evidence[$field]) ? (float) $evidence[$field] : null;
            }
            $record = ParcelFieldAssessment::query()->create([
                'farm_plot_id' => $current->id, 'farmer_id' => $farmerId, 'municipality_id' => $municipalityId,
                'crop_year' => (int) $data['crop_year'], 'season' => $data['season'], 'crop' => $data['crop'],
                'observed_on' => $data['observed_on'], 'context_fingerprint' => ClimateEvidenceContext::plot($current),
                'evidence' => $evidence, 'recorded_by' => $actor->id,
            ]);
            AuditTrail::record('recorded', 'Parcel field checks', 'Recorded a parcel field check and follow-up.', [
                'actor' => $actor, 'auditable' => $record, 'municipality_id' => $municipalityId,
                'metadata' => ['crop' => $record->crop, 'crop_year' => $record->crop_year, 'season' => $record->season,
                    'crop_stage' => $evidence['crop_stage'], 'field_condition' => $evidence['field_condition'],
                    'follow_up_status' => $evidence['follow_up_status']],
            ]);

            return $record;
        });
    }
}
