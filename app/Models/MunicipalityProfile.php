<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalityProfile extends Model
{
    protected $fillable = [
        'office_name', 'website_url', 'email', 'phone', 'office_address', 'description',
        'latitude', 'longitude', 'signatory_name', 'signatory_title',
    ];

    protected $casts = ['municipality_id' => 'integer', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
