<?php

namespace App\Providers;

use App\Models\AntiRabiesVaccination;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\Municipality;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Observers\AuditModelObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::defaultView('vendor.pagination.agri');
        Paginator::defaultSimpleView('vendor.pagination.simple-agri');

        foreach ([
            Farmer::class,
            FarmPlot::class,
            RiceSeedDistribution::class,
            AntiRabiesVaccination::class,
            FarmersCooperative::class,
            BackupFile::class,
            User::class,
            Municipality::class,
        ] as $model) {
            $model::observe(AuditModelObserver::class);
        }
    }
}
