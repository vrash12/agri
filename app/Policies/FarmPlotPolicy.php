<?php

namespace App\Policies;

use App\Models\FarmPlot;
use App\Policies\Concerns\AuthorizesMunicipalityRecords;
use Illuminate\Database\Eloquent\Model;

class FarmPlotPolicy
{
    use AuthorizesMunicipalityRecords;

    protected function municipalityId(Model $record): ?int
    {
        /** @var FarmPlot $record */
        $municipalityId = $record->farmer?->municipality_id;

        return $municipalityId === null ? null : (int) $municipalityId;
    }
}
