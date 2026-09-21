<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class BarangayBoundaryReferences
{
    private const REFERENCES = [
        [
            'names' => ['Ramos'],
            'province' => 'Tarlac',
            'location_label' => 'Ramos, Tarlac',
            'file' => 'seeders/data/ramos_barangay_reference_boundaries.geojson',
            'sha256' => 'cd34baa1f643b1dcce5aedbff2011cf17327c6c6a7b7fc38963b3655b158844f',
        ],
        [
            'names' => ['Baguio City', 'Baguio', 'City of Baguio'],
            'province' => 'Baguio City',
            'location_label' => 'Baguio City',
            'file' => 'seeders/data/baguio_barangay_reference_boundaries.geojson',
            'sha256' => '351c9f3d339f88a068f6b9373e88f5dda849d86899074a08c0d3a76a6d990282',
        ],
    ];

    public function __construct(private MunicipalityAccess $access)
    {
    }

    /** @return list<int> */
    public function availableMunicipalityIds(User $user): array
    {
        return $this->access->scopeMunicipalities($this->supportedQuery(), $user)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function forMunicipality(Municipality $municipality): array
    {
        // The province foreign key resolves geographic identity; the legacy display string is never used.
        $reference = null;
        foreach (self::REFERENCES as $candidate) {
            if ($this->supportedQuery([$candidate])->whereKey($municipality->id)->exists()) {
                $reference = $candidate;
                break;
            }
        }
        $available = $reference !== null;
        $payload = [
            'municipality_id' => (int) $municipality->id,
            'available' => $available,
            'type' => 'FeatureCollection',
            'features' => [],
        ];
        if (! $available) {
            return $payload;
        }

        $path = database_path($reference['file']);
        if (! is_file($path) || filesize($path) > 100000) {
            throw new RuntimeException('The barangay reference file is missing or oversized.');
        }
        $contents = file_get_contents($path);
        if ($contents === false || ! hash_equals($reference['sha256'], hash('sha256', $contents))) {
            throw new RuntimeException('The barangay reference checksum does not match.');
        }

        $collection = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);

        return array_merge($payload, [
            'location_label' => $reference['location_label'],
            'features' => $collection['features'],
            'source' => [
                'label' => 'James Faeldon / PSA–NAMRIA via OCHA, 2023 dataset',
                'url' => 'https://github.com/faeldon/philippines-json-maps/tree/8eeead560246863c8c820c31ca6fbca81a279477',
                'note' => 'Simplified planning reference. Confirm boundary positions with the '.$reference['location_label'].' LGU before field decisions. This layer does not assign parcels or change official municipality boundaries.',
            ],
        ]);
    }

    private function supportedQuery(array $references = self::REFERENCES): Builder
    {
        // Group source identities so the OR clauses cannot bypass municipality scope.
        return Municipality::query()->active()->where(function (Builder $query) use ($references): void {
            foreach ($references as $reference) {
                $query->orWhere(function (Builder $identity) use ($reference): void {
                    $identity->whereIn('name', $reference['names'])
                        ->whereHas('supervisingProvince', fn (Builder $province) => $province->active()->where('name', $reference['province']));
                });
            }
        });
    }
}
