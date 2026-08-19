<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_LABELS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'membership_updated' => 'Membership changed',
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'login_failed' => 'Failed sign-in',
        'login_blocked' => 'Blocked sign-in',
        'exported' => 'Exported',
    ];

    protected $fillable = [
        'user_id',
        'municipality_id',
        'actor_name',
        'actor_email',
        'actor_role',
        'event',
        'module',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'municipality_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function scopeEvent(Builder $query, ?string $event): Builder
    {
        return $query->when($event, fn (Builder $builder) => $builder->where('event', $event));
    }

    public function scopeModule(Builder $query, ?string $module): Builder
    {
        return $query->when($module, fn (Builder $builder) => $builder->where('module', $module));
    }

    public function getEventLabelAttribute(): string
    {
        return self::EVENT_LABELS[$this->event]
            ?? str($this->event)->replace('_', ' ')->title();
    }

    public function getEventToneAttribute(): string
    {
        return match ($this->event) {
            'created', 'login' => 'green',
            'updated', 'membership_updated', 'exported' => 'blue',
            'deleted', 'login_failed', 'login_blocked' => 'red',
            'logout' => 'amber',
            default => 'neutral',
        };
    }
}
