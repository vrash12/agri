<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmersCooperative extends Model
{
    use HasFactory;

    protected $table = 'farmers_cooperatives';

    protected $fillable = [
        'municipality_id',
        'name',
        'chairperson',
        'contact_number',
        'address',
        'description',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function farmers()
    {
        return $this->belongsToMany(
            Farmer::class,
            'cooperative_farmer',
            'farmers_cooperative_id',
            'farmer_id'
        )->withTimestamps();
    }

    public function machineries(): HasMany
    {
        return $this->hasMany(
            AgriculturalMachinery::class,
            'farmers_cooperative_id'
        );
    }
}
