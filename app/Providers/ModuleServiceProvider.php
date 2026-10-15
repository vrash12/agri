<?php

namespace App\Providers;

use App\Modules\Assistant\Contracts\OfficeRecordReader;
use App\Modules\Land\Application\CropSectionHarvestGuard;
use App\Modules\Land\Contracts\HarvestSectionGuard;
use App\Modules\Reporting\Application\AssistantRecords;
use Illuminate\Support\ServiceProvider;

/** Composition root: modules depend on contracts, not provider implementations. */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OfficeRecordReader::class, AssistantRecords::class);
        $this->app->bind(HarvestSectionGuard::class, CropSectionHarvestGuard::class);
    }
}
