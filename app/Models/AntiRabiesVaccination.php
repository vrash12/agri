<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AntiRabiesVaccination extends Model
{
    protected $fillable = [
        'municipality_id',

        // Owner
        'owner_name',
        'barangay',
        'birthday',

        // Pet
        'pet_type',
        'pet_breed',
        'pet_name',
        'pet_color',

        // Vaccination
        'vaccination_year',
        'vaccination_date',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'birthday' => 'date',
        'vaccination_date' => 'date',
        'vaccination_year' => 'integer',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
