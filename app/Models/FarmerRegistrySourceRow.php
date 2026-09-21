<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmerRegistrySourceRow extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_OUTSIDE_LGU = 'outside_lgu';

    public const STATUS_DELISTED = 'delisted';

    protected $fillable = [
        'municipality_id',
        'farmer_id',
        'source_file_sha256',
        'source_sheet',
        'source_row',
        'record_status',
        'source_ffrs',
        'source_rsbsa_no',
        'parcel_no',
        'payload',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'farmer_id' => 'integer',
        'source_row' => 'integer',
        'payload' => 'array',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }
}
