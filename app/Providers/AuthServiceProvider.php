<?php

namespace App\Providers;

use App\Models\AntiRabiesVaccination;
use App\Models\BackupFile;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Models\FarmPlot;
use App\Models\RiceSeedDistribution;
use App\Models\User;
use App\Policies\AntiRabiesVaccinationPolicy;
use App\Policies\BackupFilePolicy;
use App\Policies\FarmerPolicy;
use App\Policies\FarmersCooperativePolicy;
use App\Policies\FarmPlotPolicy;
use App\Policies\RiceSeedDistributionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Farmer::class => FarmerPolicy::class,
        FarmPlot::class => FarmPlotPolicy::class,
        RiceSeedDistribution::class => RiceSeedDistributionPolicy::class,
        AntiRabiesVaccination::class => AntiRabiesVaccinationPolicy::class,
        FarmersCooperative::class => FarmersCooperativePolicy::class,
        BackupFile::class => BackupFilePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
    }
}
