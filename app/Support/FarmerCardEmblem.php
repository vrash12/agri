<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Builder;

/** Resolve the issuing-office seal from the farmer's active supervising scope. */
final class FarmerCardEmblem
{
    /** @return array{path: string, alt: string, municipal: bool} */
    public function forFarmer(Farmer $farmer): array
    {
        $ramos = $farmer->municipality_id !== null && Municipality::query()->active()
            ->whereKey($farmer->municipality_id)->where('name', 'Ramos')
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active()->where('name', 'Tarlac'))
            ->exists();

        return $ramos
            ? ['path' => 'images/branding/ramos-municipality-seal.jpg',
                'alt' => 'Seal of the Municipality of Ramos, Tarlac', 'municipal' => true]
            : ['path' => 'images/branding/philippines-coat-of-arms.png',
                'alt' => 'Coat of arms of the Republic of the Philippines', 'municipal' => false];
    }
}
