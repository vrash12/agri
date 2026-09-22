<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\FarmerRegistrySourceRow;
use Illuminate\Support\Facades\Schema;

final class FarmerCardLocations
{
    public function forFarmer(Farmer $farmer): string
    {
        if (! Schema::hasTable('farmer_registry_source_rows')) {
            return 'Parcel address not recorded';
        }

        $addresses = [];
        foreach ($farmer->registrySourceRows()->where('municipality_id', $farmer->municipality_id)
            ->whereIn('record_status', [FarmerRegistrySourceRow::STATUS_ACTIVE, FarmerRegistrySourceRow::STATUS_OUTSIDE_LGU])
            ->orderBy('id')->select(['id', 'payload'])->lazyById(200) as $row) {
            $address = self::fromPayload($row->payload ?? []);
            if ($address !== '') {
                $addresses[mb_strtolower($address)] ??= $address;
            }
        }

        return $addresses ? implode(' / ', $addresses) : 'Parcel address not recorded';
    }

    public static function fromPayload(array $payload): string
    {
        $parts = [];
        foreach ([1, 2, 3] as $part) {
            $value = $payload['PARCEL ADDRESS '.$part] ?? null;
            if (! is_scalar($value)) {
                continue;
            }
            $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
            if ($value !== '' && ! in_array(mb_strtolower($value), ['null', 'unknown', 'n/a', '-'], true)) {
                $parts[] = $value;
            }
        }

        return implode(', ', $parts);
    }
}
