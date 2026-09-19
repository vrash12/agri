<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class FarmerPortalAccount extends Authenticatable
{
    protected $fillable = [
        'farmer_id',
        'municipality_id',
        'login_id',
        'password',
        'activation_code_hash',
        'activation_expires_at',
        'activated_at',
        'is_active',
        'session_version',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'activation_code_hash',
        'session_version',
    ];

    protected $attributes = [
        'is_active' => true,
        'session_version' => 1,
    ];

    protected $casts = [
        'farmer_id' => 'integer',
        'municipality_id' => 'integer',
        'is_active' => 'boolean',
        'session_version' => 'integer',
        'activation_expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    // Farmer sessions deliberately do not offer persistent "remember me" cookies.
    protected $rememberTokenName = '';

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public static function canonicalLoginId(Farmer|int $farmer): string
    {
        $id = $farmer instanceof Farmer ? $farmer->getKey() : $farmer;

        return 'AGRI-F-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function hasUsableScope(): bool
    {
        if (! $this->is_active || $this->farmer_id < 1 || $this->municipality_id < 1) {
            return false;
        }

        // Re-read ownership instead of trusting previously loaded relationships.
        // A transferred farmer needs a fresh staff-verified activation in the new office.
        return $this->farmer()->where('municipality_id', $this->municipality_id)
            ->whereHas('municipality', fn (Builder $query) => $query->active()
                ->whereHas('supervisingProvince', fn (Builder $province) => $province->active()))
            ->exists();
    }
}
