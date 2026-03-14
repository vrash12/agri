<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Farmer extends Model
{
    protected $table = 'farmers';

    protected $fillable = [
        'rsbsa_no',
        'ffrs',

        'last_name',
        'first_name',
        'middle_name',
        'ext_name',

        'date_of_birth',
        'contact_number',
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

        // ✅ only if you add these columns to farmers table
        'is_delisted',
        'delisted_at',
        'delisted_source',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'farm_area_ha'  => 'decimal:2',

        'is_arb' => 'boolean',
        'is_4ps' => 'boolean',
        'is_ip'  => 'boolean',
        'is_pwd' => 'boolean',
        'is_sc'  => 'boolean',
        'is_ofw' => 'boolean',

        // ✅ only if you add these columns to farmers table
        'is_delisted' => 'boolean',
        'delisted_at' => 'datetime',
    ];

    public function riceSeedDistributions()
    {
        return $this->hasMany(\App\Models\RiceSeedDistribution::class, 'farmer_id');
    }
}
