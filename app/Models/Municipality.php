<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Municipality extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'province',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function farmers(): HasMany
    {
        return $this->hasMany(Farmer::class);
    }

    public function riceSeedDistributions(): HasMany
    {
        return $this->hasMany(RiceSeedDistribution::class);
    }

    public function antiRabiesVaccinations(): HasMany
    {
        return $this->hasMany(AntiRabiesVaccination::class);
    }

    public function farmersCooperatives(): HasMany
    {
        return $this->hasMany(FarmersCooperative::class);
    }

    public function agriculturalMachineries(): HasMany
    {
        return $this->hasMany(AgriculturalMachinery::class);
    }

    public function backupFiles(): HasMany
    {
        return $this->hasMany(BackupFile::class);
    }

    public function boundaries(): HasMany
    {
        return $this->hasMany(MunicipalityBoundary::class);
    }

    public function activeBoundary(): HasOne
    {
        return $this->hasOne(MunicipalityBoundary::class)
            ->where('status', MunicipalityBoundary::STATUS_ACTIVE)
            ->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
