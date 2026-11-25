<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An archived period report: aggregate figures for a fixed scope and period.
 *
 * The covered municipality ids are stored with the report so access can be
 * rechecked against the viewer's current scope, not trusted from the scope
 * columns alone.
 */
class GeneratedReport extends Model
{
    public const PERIOD_TYPES = ['month' => 'Monthly', 'quarter' => 'Quarterly', 'year' => 'Annual'];

    public const SCOPE_TYPES = ['municipality', 'province', 'region', 'all'];

    public const SOURCE_SCHEDULED = 'scheduled';

    public const SOURCE_ON_DEMAND = 'on_demand';

    protected $fillable = [
        'period_type', 'period_start', 'period_end', 'scope_type', 'municipality_id',
        'province_id', 'region_id', 'municipality_ids', 'title', 'summary', 'source',
        'dedupe_key', 'generated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'municipality_id' => 'integer',
        'province_id' => 'integer',
        'region_id' => 'integer',
        'municipality_ids' => 'array',
        'summary' => 'array',
        'generated_by' => 'integer',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function periodTypeLabel(): string
    {
        return self::PERIOD_TYPES[$this->period_type] ?? 'Period';
    }

    public function sourceLabel(): string
    {
        return $this->source === self::SOURCE_SCHEDULED ? 'Automatic monthly' : 'Generated on request';
    }
}
