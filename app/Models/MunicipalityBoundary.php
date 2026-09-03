<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalityBoundary extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'municipality_id',
        'name',
        'geojson',
        'color',
        'status',
        'area_ha',
        'centroid_lat',
        'centroid_lng',
        'min_lat',
        'max_lat',
        'min_lng',
        'max_lng',
        'vertex_count',
        'created_by',
        'updated_by',
        'archived_at',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'geojson' => 'array',
        'area_ha' => 'float',
        'centroid_lat' => 'float',
        'centroid_lng' => 'float',
        'min_lat' => 'float',
        'max_lat' => 'float',
        'min_lng' => 'float',
        'max_lng' => 'float',
        'vertex_count' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_ACTIVE]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
