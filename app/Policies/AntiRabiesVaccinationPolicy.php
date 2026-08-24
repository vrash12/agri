<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesMunicipalityRecords;

class AntiRabiesVaccinationPolicy
{
    use AuthorizesMunicipalityRecords;

    protected function allowsProvincialVeterinaryOffice(): bool
    {
        return true;
    }
}
