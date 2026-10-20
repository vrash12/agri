<?php

namespace App\Modules\Farmers\Application;

use App\Models\Farmer;
use Illuminate\Support\Facades\Schema;

final class FarmerCardIssuer
{
    public function __construct(private FarmerCardEmblem $emblems) {}

    public function forFarmer(Farmer $farmer): array
    {
        $municipality = $farmer->municipality;
        $profile = $municipality && Schema::hasTable('municipality_profiles') ? $municipality->profile : null;
        $emblem = $this->emblems->forFarmer($farmer);

        return [
            'configured' => $profile !== null,
            'office_name' => $profile?->office_name ?: ($emblem['municipal'] ? 'Municipal Agriculture Office' : 'Provincial Agriculture Office'),
            'location' => $municipality?->name ?: $farmer->farm_municipality ?: 'Municipality not recorded',
            'province' => $municipality?->supervisingProvince?->name ?: $municipality?->province ?: $farmer->farm_province,
            'website_url' => $profile?->website_url,
            'email' => $profile?->email,
            'phone' => $profile?->phone,
            'office_address' => $profile?->office_address,
            'logo_url' => $profile?->logo_path ? route('settings.lgu.logo', $municipality) : asset($emblem['path']),
            'logo_alt' => $profile?->logo_path ? $municipality->name.' LGU logo' : $emblem['alt'],
        ];
    }
}
