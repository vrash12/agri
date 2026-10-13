<?php

namespace App\Policies;

use App\Models\HarvestRecord;
use App\Models\User;
use App\Policies\Concerns\AuthorizesMunicipalityRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Municipality isolation for harvest records, plus one rule of its own.
 *
 * A harvest projected from an assistance release is owned by that release. The
 * release is where its figures are entered, and saving the release rewrites the
 * projected record from them. Allowing the harvest to be edited here as well would
 * mean the next save of the release silently discards the edit — the operator would
 * see their change accepted and then find it gone, with nothing to explain why.
 *
 * So a projected record is readable here and changed at its source. The interface
 * says so and links to the release; this policy is what makes that true rather than
 * merely suggested, because hidden buttons are not enforcement.
 */
class HarvestRecordPolicy
{
    use AuthorizesMunicipalityRecords { view as protected municipalityView; }

    public function update(User $user, Model $record): bool
    {
        return ! $this->isProjected($record)
            && $this->authorizesManagement($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return ! $this->isProjected($record)
            && $this->authorizesManagement($user, $record);
    }

    private function isProjected(Model $record): bool
    {
        return $record instanceof HarvestRecord && $record->isProjected();
    }

    public function view(User $user, Model $record): bool
    {
        if (! $this->municipalityView($user, $record)) {
            return false;
        }

        if (! Schema::hasColumn('harvest_records', 'parcel_crop_season_id')
            || ! $record->parcel_crop_season_id) {
            return true;
        }

        $season = $record->parcelCropSeason()->with('plot.farmer')->first();

        if ($season === null) {
            // Keep an orphaned legacy reference manageable so staff can clear it;
            // municipality scope above still protects the record.
            return true;
        }

        if (! $user->can('view', $season)) {
            return false;
        }

        // A missing key is still editable so staff can clear an orphaned reference;
        // normal writes validate the key against the current season JSON.
        return true;
    }

    /**
     * The management check the shared concern applies, reused rather than restated so
     * the role and scope rules cannot drift from every other module's.
     */
    private function authorizesManagement(User $user, Model $record): bool
    {
        return $user->canManageOperationalData() && $this->view($user, $record);
    }
}
