<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesMunicipalityRecords;

/**
 * Rice Seed Distribution Sheet batches follow the same municipality ownership
 * rules as the assistance releases they group: municipal accounts see only their
 * own municipality, provincial accounts their province, and System Owner and
 * Super Administrator oversight stays read-only through
 * `User::canManageOperationalData()`. `provincial_vet` is denied by the concern.
 */
class RiceDistributionBatchPolicy
{
    use AuthorizesMunicipalityRecords;
}
