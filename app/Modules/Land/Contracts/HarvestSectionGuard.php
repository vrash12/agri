<?php

namespace App\Modules\Land\Contracts;

use App\Models\User;

interface HarvestSectionGuard
{
    /** Lock and reauthorize the selected section inside the caller's transaction. */
    public function assertMatches(array $validated, User $user): void;
}
