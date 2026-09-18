<?php

namespace App\Providers;

use App\Models\AgriculturalMachinery;
use App\Models\AntiRabiesVaccination;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\HarvestRecord;
use App\Models\Municipality;
use App\Models\RiceDistributionBatch;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Observers\AuditModelObserver;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // The breached-password check runs inside a form submission, so it gets a
        // short timeout instead of Laravel's 30-second default. The verifier treats
        // an unreachable service as "not breached", so an office with a slow or
        // absent connection can still create accounts.
        $this->app->singleton(
            UncompromisedVerifier::class,
            fn ($app): NotPwnedVerifier => new NotPwnedVerifier($app[HttpFactory::class], 3)
        );
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
            AgriculturalMachinery::class,
            Farmer::class,
            FarmPlot::class,
            HarvestRecord::class,
            RiceSeedDistribution::class,
            RiceDistributionBatch::class,
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
