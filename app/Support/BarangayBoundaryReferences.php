<?php

namespace App\Support;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class BarangayBoundaryReferences
{
    private const FILE = 'seeders/data/ramos_barangay_reference_boundaries.geojson';

    private const SHA256 = 'cd34baa1f643b1dcce5aedbff2011cf17327c6c6a7b7fc38963b3655b158844f';

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
        $available = $this->supportedQuery()->whereKey($municipality->id)->exists();
        $payload = [
            'municipality_id' => (int) $municipality->id,
            'available' => $available,
            'type' => 'FeatureCollection',
            'features' => [],
        ];
        if (! $available) {
            return $payload;
        }

        $path = database_path(self::FILE);
        if (! is_file($path) || filesize($path) > 100000) {
            throw new RuntimeException('The Ramos barangay reference file is missing or oversized.');
        }
        $contents = file_get_contents($path);
        if ($contents === false || ! hash_equals(self::SHA256, hash('sha256', $contents))) {
            throw new RuntimeException('The Ramos barangay reference checksum does not match.');
        }

        $collection = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);

        return array_merge($payload, [
            'features' => $collection['features'],
            'source' => [
                'label' => 'James Faeldon / PSA–NAMRIA via OCHA, 2023 dataset',
                'url' => 'https://github.com/faeldon/philippines-json-maps/tree/8eeead560246863c8c820c31ca6fbca81a279477',
                'note' => 'Simplified planning reference. Confirm boundary positions with the Ramos LGU before field decisions. This layer does not assign parcels or change official municipality boundaries.',
            ],
        ]);
    }

    private function supportedQuery(): Builder
    {
        return Municipality::query()->active()->where('name', 'Ramos')
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active()->where('name', 'Tarlac'));
    }
}
