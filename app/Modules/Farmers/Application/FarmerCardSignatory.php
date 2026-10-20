<?php

namespace App\Modules\Farmers\Application;

use App\Models\Farmer;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the office signatory printed on the back of a farmer registry card.
 *
 * A saved LGU profile takes precedence, including a deliberately blank pair.
 * Legacy config entries remain a fallback for offices without profiles, using
 * municipality name and supervising province to avoid same-name office matches.
 */
final class FarmerCardSignatory
{
    /** @return array{name: string, title: string}|null */
    public function forFarmer(Farmer $farmer): ?array
    {
        if ($farmer->municipality_id === null) {
            return null;
        }

        $municipality = Municipality::query()->active()->whereKey($farmer->municipality_id)
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active())->first();

        return $municipality ? $this->forMunicipality($municipality) : null;
    }

    /** @return array{name: string, title: string}|null */
    public function forMunicipality(Municipality $municipality): ?array
    {
        if (! $municipality->is_active || ! $municipality->supervisingProvince?->is_active) {
            return null;
        }
        if (Schema::hasTable('municipality_profiles') && ($profile = $municipality->profile)) {
            return $profile->signatory_name && $profile->signatory_title
                ? ['name' => $profile->signatory_name, 'title' => $profile->signatory_title] : null;
        }

        foreach ((array) config('farmer_card.signatories', []) as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            $title = trim((string) ($entry['title'] ?? ''));
            $municipalities = array_values(array_filter((array) ($entry['municipalities'] ?? []), 'is_string'));
            $province = (string) ($entry['province'] ?? '');

            // An incomplete entry prints nothing rather than a half-filled signature block.
            if ($name === '' || $title === '' || $municipalities === [] || $province === '') {
                continue;
            }

            $matches = Municipality::query()->active()
                ->whereKey($municipality->getKey())
                ->whereIn('name', $municipalities)
                ->whereHas('supervisingProvince', fn (Builder $query) => $query->active()->where('name', $province))
                ->exists();

            if ($matches) {
                return ['name' => $name, 'title' => $title];
            }
        }

        return null;
    }
}
