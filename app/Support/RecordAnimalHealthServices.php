<?php

namespace App\Support;

use App\Models\AntiRabiesVaccination;
use Illuminate\Support\Facades\Schema;

final class RecordAnimalHealthServices
{
    public function __construct(private ConcurrentWrite $concurrentWrite)
    {
    }

    /** @param  array<string, mixed>  $validated */
    public function record(array $validated, int $municipalityId): int
    {
        $owner = array_intersect_key($validated, array_flip(['owner_name', 'barangay', 'birthday']));
        $hasYear = Schema::hasColumn('anti_rabies_vaccinations', 'vaccination_year');

        return $this->concurrentWrite->transaction(function () use ($validated, $owner, $municipalityId, $hasYear): int {
            foreach ($validated['animals'] as $animal) {
                $data = array_intersect_key($animal, AnimalHealthServiceRules::animal());
                $data = array_merge($data, $owner, ['municipality_id' => $municipalityId]);
                if ($hasYear) {
                    $data['vaccination_year'] = (int) date('Y', strtotime($data['vaccination_date']));
                }
                // One existing, audited service record per species/group keeps
                // species totals, edits and prior-owner lookup compatible.
                AntiRabiesVaccination::create($data);
            }

            return count($validated['animals']);
        });
    }
}
