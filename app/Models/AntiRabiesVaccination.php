<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntiRabiesVaccination extends Model
{
    protected $fillable = [
        // Owner
        'owner_name',
        'barangay',
        'birthday',

        // Pet
        'pet_breed',
        'pet_name',
        'pet_color',

        // Vaccination
        'vaccination_year',
        'vaccination_date',
    ];

    protected $casts = [
        'birthday' => 'date',
        'vaccination_date' => 'date',
        'vaccination_year' => 'integer',
    ];
}