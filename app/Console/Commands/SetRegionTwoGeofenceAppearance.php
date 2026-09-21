<?php

namespace App\Console\Commands;

use App\Support\RegionTwoGeofenceAppearance;
use Illuminate\Console\Command;
use Throwable;

class SetRegionTwoGeofenceAppearance extends Command
{
    protected $signature = 'geofences:region-two-white {--apply : Save the reviewed appearance} {--actor= : Active System Owner ID for attribution}';

    protected $description = 'Preview or apply white fill at 20% opacity to the 93 Region II municipality/city geofences';

    public function handle(RegionTwoGeofenceAppearance $appearance): int
    {
        try {
            $result = $appearance->run((bool) $this->option('apply'), $this->option('actor') ? (int) $this->option('actor') : null);
            $this->table(['Province / city', 'Active boundaries', $this->option('apply') ? 'Changed' : 'Would change'], collect($result)->map(fn ($row, $name) => [$name, $row['boundaries'], $row['changed']])->values()->all());
            $this->info($this->option('apply') ? 'Appearance saved. Geometry and regional assignments were preserved.' : 'Preview only; no records changed. Use --apply --actor=ID after reviewing and backing up the target database.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
