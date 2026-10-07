<?php

namespace App\Support;

use App\Models\Farmer;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves the office signatory printed on the back of a farmer registry card.
 *
 * Each office prints its own head, so a farmer only receives the signatory
 * configured for their municipality in `config/farmer_card.php`. Identity uses
 * the municipality name and its supervising province record, the same rule
 * `BarangayBoundaryReferences` uses for Ramos.
 */
final class FarmerCardSignatory
{
    /** @return array{name: string, title: string}|null */
    public function forFarmer(Farmer $farmer): ?array
    {
        if ($farmer->municipality_id === null) {
            return null;
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
                ->whereKey($farmer->municipality_id)
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
