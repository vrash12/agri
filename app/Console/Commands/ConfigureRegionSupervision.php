<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\RegionSupervision;
use Illuminate\Console\Command;

class ConfigureRegionSupervision extends Command
{
    protected $signature = 'region-access:configure {--owner= : Active System Owner ID for authorization and audit} {--region= : Configure only this region code; use region4a for CALABARZON or mimaropa for MIMAROPA}';

    protected $description = 'Configure regional supervision after a database backup; CALABARZON and MIMAROPA require --region';

    public function handle(RegionSupervision $supervision): int
    {
        $owner = User::query()->findOrFail((int) $this->option('owner'));
        $regions = $supervision->configure($owner, $this->option('region'));
        $this->info('Configured '.$regions->count().' regions. Existing accounts and municipality ownership are unchanged.');

        return self::SUCCESS;
    }
}
