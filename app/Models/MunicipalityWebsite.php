<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalityWebsite extends Model
{
    protected $fillable = ['headline', 'tagline', 'welcome_text', 'office_hours', 'services', 'announcements', 'is_published'];

    protected $casts = ['municipality_id' => 'integer', 'is_published' => 'boolean', 'services' => 'array', 'announcements' => 'array'];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereHas('municipality', fn ($query) => $query->active()
                ->whereHas('supervisingProvince', fn ($query) => $query->active())
                ->whereHas('profile'));
    }
}
