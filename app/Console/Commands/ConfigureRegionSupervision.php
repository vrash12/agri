<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\RegionSupervision;
use Illuminate\Console\Command;

class ConfigureRegionSupervision extends Command
{
    protected $signature = 'region-access:configure {--owner= : Active System Owner ID for authorization and audit} {--region= : Configure only this region code; use region4a for CALABARZON}';

    protected $description = 'Explicitly configure the approved Region I, II, III and Negros Island supervision after a database backup';

    public function handle(RegionSupervision $supervision): int
    {
        $owner = User::query()->findOrFail((int) $this->option('owner'));
        $regions = $supervision->configure($owner, $this->option('region'));
        $this->info('Configured '.$regions->count().' regions. Existing accounts and municipality ownership are unchanged.');

        return self::SUCCESS;
    }
}
