<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Farmer extends Model
{
    protected $table = 'farmers';

    protected $hidden = [
        'public_map_token',
    ];

    protected $fillable = [
        'municipality_id',

        'rsbsa_no',
        'ffrs',

        'last_name',
        'first_name',
        'middle_name',
        'ext_name',
        'owner_name',

        'date_of_birth',
        'contact_number',
        'profile_photo_path',
        'gender',

        'farm_location',
        'farm_province',
        'farm_municipality',

        'ecosystem',
        'ecosystem_source',

        'is_arb',
        'is_4ps',
        'is_ip',
        'is_pwd',
        'is_sc',
        'is_ofw',

        'farm_area_ha',
    ];

    protected $casts = [
        'municipality_id' => 'integer',

        'date_of_birth' => 'date',
        'farm_area_ha' => 'decimal:2',

        'is_arb' => 'boolean',
        'is_4ps' => 'boolean',
        'is_ip' => 'boolean',
        'is_pwd' => 'boolean',
        'is_sc' => 'boolean',
        'is_ofw' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Farmer $farmer) {
            if (! $farmer->public_map_token) {
                $farmer->public_map_token = Str::random(40);
            }
        });
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function riceSeedDistributions(): HasMany
    {
        return $this->hasMany(
            RiceSeedDistribution::class,
            'farmer_id'
        );
    }

    public function cooperatives(): BelongsToMany
    {
        return $this->belongsToMany(
            FarmersCooperative::class,
            'cooperative_farmer',
            'farmer_id',
            'farmers_cooperative_id'
        )->withTimestamps();
    }

    public function farmPlots(): HasMany
    {
        return $this->hasMany(
            FarmPlot::class,
            'farmer_id'
        );
    }

    /**
     * Stable, human-readable identifier used on the local farmer card.
     */
    public function getRegistryIdAttribute(): string
    {
        return 'PAIS-FRM-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }
}
