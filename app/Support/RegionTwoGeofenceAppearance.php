<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RegionTwoGeofenceAppearance
{
    private const SCOPES = ['Batanes' => 6, 'Cagayan' => 29, 'Isabela' => 36, 'Nueva Vizcaya' => 15, 'Quirino' => 6, 'Santiago City' => 1];

    /** @return array<string, array{boundaries: int, changed: int}> */
    public function run(bool $apply, ?int $actorId): array
    {
        return Cache::lock('municipality-boundaries:activation', 120)->block(15, fn () => DB::transaction(function () use ($apply, $actorId): array {
            $actor = $apply ? User::query()->lockForUpdate()->find($actorId) : null;
            if ($apply && (! $actor || ! $actor->isSystemOwner() || ! $actor->hasUsableScope())) {
                throw new RuntimeException('Choose an active System Owner with --actor for this cross-province setup.');
            }
            $result = [];
            foreach (self::SCOPES as $name => $expected) {
                $province = Province::query()->where('name', $name)->lockForUpdate()->sole();
                if (! $province->is_active) {
                    throw new RuntimeException('Inactive province scope: '.$name);
                }
                $municipalities = Municipality::query()->where('province_id', $province->id)->lockForUpdate()->get();
                if ($municipalities->count() !== $expected || $municipalities->contains(fn ($item) => ! $item->is_active)) {
                    throw new RuntimeException('Review municipality coverage for '.$name.' before changing styles.');
                }
                $boundaries = MunicipalityBoundary::active()->whereIn('municipality_id', $municipalities->modelKeys())->lockForUpdate()->get();
                if ($boundaries->count() !== $expected || $boundaries->pluck('municipality_id')->unique()->count() !== $expected) {
                    throw new RuntimeException('Expected exactly one active boundary per municipality in '.$name.'.');
                }
                $changed = 0;
                foreach ($boundaries as $boundary) {
                    if (strtoupper($boundary->color) === '#FFFFFF' && (float) $boundary->fill_opacity === .2) {
                        continue;
                    }
                    $changed++;
                    if (! $apply) {
                        continue;
                    }
                    $before = $boundary->only(['color', 'fill_opacity']);
                    $boundary->update(['color' => '#FFFFFF', 'fill_opacity' => .2, 'updated_by' => $actor->id]);
                    $audit = AuditTrail::record('updated', 'Municipality geofences', 'Applied Region II white geofence appearance.', [
                        'actor' => $actor, 'auditable' => $boundary, 'municipality_id' => $boundary->municipality_id,
                        'old_values' => $before, 'new_values' => $boundary->only(['color', 'fill_opacity']),
                        'metadata' => ['change' => 'map_style', 'setup' => 'region_two_white'],
                    ]);
                    if (! $audit) {
                        throw new RuntimeException('Style audit could not be recorded. Setup rolled back.');
                    }
                    DB::afterCommit(fn () => Cache::forget('municipality-boundary:active:v1:'.$boundary->municipality_id));
                }
                $result[$name] = ['boundaries' => $expected, 'changed' => $changed];
            }

            return $result;
        }, 3));
    }
}
