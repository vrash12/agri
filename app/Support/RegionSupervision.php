<?php

namespace App\Support;

use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

final class RegionSupervision
{
    /** Explicitly requested supervision; cities retain their own province-level scope. */
    public const GROUPS = [
        'region1' => ['name' => 'Region I — Ilocos Region', 'provinces' => ['Ilocos Norte', 'Ilocos Sur', 'La Union', 'Pangasinan'], 'cities' => []],
        'region2' => ['name' => 'Region II — Cagayan Valley', 'provinces' => ['Batanes', 'Cagayan', 'Isabela', 'Nueva Vizcaya', 'Quirino'], 'cities' => ['Santiago City']],
        'region3' => ['name' => 'Region III — Central Luzon', 'provinces' => ['Aurora', 'Bataan', 'Bulacan', 'Nueva Ecija', 'Pampanga', 'Tarlac', 'Zambales'], 'cities' => ['Angeles City', 'Olongapo City']],
        'negros-island' => ['name' => 'Negros Island Region', 'provinces' => ['Negros Occidental', 'Negros Oriental', 'Siquijor'], 'cities' => ['Bacolod City']],
    ];

    public const CALABARZON = ['name' => 'Region IV-A — CALABARZON', 'provinces' => ['Cavite', 'Laguna', 'Batangas', 'Rizal', 'Quezon'], 'cities' => ['Lucena City']];

    public function __construct(private ConcurrentWrite $writes)
    {
    }

    /** @return Collection<int, Region> */
    public function configure(User $owner, ?string $regionCode = null): Collection
    {
        // Preserve the original command's scope; new regions require an explicit selection.
        $available = self::GROUPS + ['region4a' => self::CALABARZON];
        if ($regionCode !== null && ! isset($available[$regionCode])) {
            throw new RuntimeException('Unknown region supervision code: '.$regionCode);
        }
        $groups = $regionCode === null ? self::GROUPS : [$regionCode => $available[$regionCode]];

        return $this->writes->transaction(function () use ($owner, $groups): Collection {
            $actor = User::query()->lockForUpdate()->findOrFail($owner->id);
            abort_unless($actor->isSystemOwner() && $actor->hasUsableScope(), 403);
            $regions = collect();
            foreach ($groups as $code => $group) {
                $region = Region::query()->where('code', $code)->lockForUpdate()->first();
                if ($region && (! $region->is_active || $region->name !== $group['name'])) {
                    throw new RuntimeException('The region conflicts with the requested active scope: '.$code);
                }
                if (! $region) {
                    $region = Region::create(['code' => $code, 'name' => $group['name'], 'is_active' => true]);
                    $this->audit($actor, $region, ['code' => $code, 'name' => $group['name']]);
                }
                foreach ([...$group['provinces'], ...$group['cities']] as $name) {
                    $province = Province::query()->where('name', $name)->lockForUpdate()->sole();
                    if (! $province->is_active || ($province->region_id !== null && $province->region_id !== $region->id)) {
                        throw new RuntimeException('An inactive or differently assigned scope must be reviewed first: '.$name);
                    }
                    if ($province->region_id === null) {
                        $province->region_id = $region->id;
                        $province->save();
                        $this->audit($actor, $province, ['region_id' => $region->id]);
                    }
                }
                $regions->push($region);
            }

            return $regions;
        });
    }

    private function audit(User $actor, Region|Province $record, array $values): void
    {
        // Changing the access hierarchy must never succeed without its administrative receipt.
        if (! AuditTrail::record('updated', 'Region supervision', 'Configured regional supervision for '.$record->name.'.', [
            'actor' => $actor, 'auditable' => $record, 'owner_only' => true, 'new_values' => $values,
        ])) {
            throw new RuntimeException('Regional supervision could not be audited.');
        }
    }
}
