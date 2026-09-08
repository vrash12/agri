<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | User Roles
    |--------------------------------------------------------------------------
    */

    public const ROLE_SYSTEM_OWNER = 'system_owner';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_PROVINCIAL_STAFF = 'provincial_staff';

    public const ROLE_PROVINCIAL_VET = 'provincial_vet';

    public const ROLE_MUNICIPAL_HEAD = 'municipal_head';

    public const ROLE_MUNICIPAL_STAFF = 'municipal_staff';

    /**
     * All valid system roles.
     */
    public const ROLES = [
        self::ROLE_SYSTEM_OWNER,
        self::ROLE_SUPER_ADMIN,
        self::ROLE_PROVINCIAL_STAFF,
        self::ROLE_PROVINCIAL_VET,
        self::ROLE_MUNICIPAL_HEAD,
        self::ROLE_MUNICIPAL_STAFF,
    ];

    /**
     * Provincial-level roles.
     *
     * These users can access records from all municipalities.
     */
    public const PROVINCIAL_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_PROVINCIAL_STAFF,
        self::ROLE_PROVINCIAL_VET,
    ];

    /**
     * Municipality-level roles.
     *
     * These users should only access records belonging to their municipality.
     */
    public const MUNICIPAL_ROLES = [
        self::ROLE_MUNICIPAL_HEAD,
        self::ROLE_MUNICIPAL_STAFF,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'municipality_id',
        'province_id',
        'is_active',
        'last_login_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden Attributes
    |--------------------------------------------------------------------------
    */

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'municipality_id' => 'integer',
        'province_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Municipality assigned to the user.
     *
     * Provincial-level users normally have a null municipality_id.
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the user has the given role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Determine whether the user has any of the provided roles.
     *
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Determine whether the user is the provincial super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Determine whether the user is provincial agriculture office staff.
     */
    public function isProvincialStaff(): bool
    {
        return $this->hasRole(self::ROLE_PROVINCIAL_STAFF);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function isSystemOwner(): bool
    {
        return $this->hasRole(self::ROLE_SYSTEM_OWNER);
    }

    public function requiresProvince(): bool
    {
        return $this->hasAnyRole(self::PROVINCIAL_ROLES);
    }

    public function canOverseeSystem(): bool
    {
        return $this->isActive() && ($this->isSystemOwner() || $this->isSuperAdmin());
    }

    public function hasUsableScope(): bool
    {
        if (! $this->isActive() || ! $this->hasAnyRole(self::ROLES)) {
            return false;
        }
        if ($this->isSystemOwner()) {
            return true;
        }
        if ($this->requiresProvince()) {
            return $this->province_id !== null && Province::query()->active()->whereKey($this->province_id)->exists();
        }

        return $this->municipality_id !== null && Municipality::query()->active()->whereKey($this->municipality_id)
            ->whereHas('supervisingProvince', fn (Builder $query) => $query->active())->exists();
    }

    public function canAccessProvince(?int $provinceId): bool
    {
        return $this->hasUsableScope() && ($this->isSystemOwner()
            || ($this->requiresProvince() && $provinceId !== null && $this->province_id === $provinceId));
    }

    public function getScopeLabelAttribute(): string
    {
        if ($this->isSystemOwner()) {
            return 'All supervised provinces';
        }
        if ($this->requiresProvince()) {
            return $this->province?->name ?? 'Province not assigned';
        }

        return $this->municipality?->name ?? 'Municipality not assigned';
    }

    public function scopeLabel(): string
    {
        return $this->scope_label;
    }

    /**
     * Determine whether the user belongs to the Provincial Veterinary Office.
     */
    public function isProvincialVeterinaryOffice(): bool
    {
        return $this->hasRole(self::ROLE_PROVINCIAL_VET);
    }

    /**
     * Determine whether the user has a province-wide office assignment.
     */
    public function isProvincialUser(): bool
    {
        return $this->isSystemOwner() || $this->requiresProvince();
    }

    /**
     * Determine whether the user is a head agriculturist.
     */
    public function isMunicipalHead(): bool
    {
        return $this->hasRole(self::ROLE_MUNICIPAL_HEAD);
    }

    /**
     * Determine whether the user is municipal agriculture office staff.
     */
    public function isMunicipalStaff(): bool
    {
        return $this->hasRole(self::ROLE_MUNICIPAL_STAFF);
    }

    /**
     * Determine whether the user belongs to a municipal agriculture office.
     */
    public function isMunicipalUser(): bool
    {
        return $this->hasAnyRole(self::MUNICIPAL_ROLES);
    }

    /**
     * Determine whether the user is currently active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Whether the interface may offer multiple municipalities within the user's scope.
     * This is not a global query bypass: always apply MunicipalityAccess.
     */
    public function canAccessAllMunicipalities(): bool
    {
        return $this->isProvincialUser();
    }

    /**
     * Determine whether the user can access a particular municipality.
     */
    public function canAccessMunicipality(?int $municipalityId): bool
    {
        if (! $this->hasUsableScope()) {
            return false;
        }
        if ($this->isSystemOwner()) {
            return true;
        }
        if ($municipalityId === null) {
            return false;
        }
        if ($this->requiresProvince()) {
            return Municipality::query()->active()->whereKey($municipalityId)->where('province_id', $this->province_id)->exists();
        }

        return (int) $this->municipality_id === (int) $municipalityId;
    }

    /**
     * Determine whether the user can manage provincial and municipal users.
     */
    public function canManageAllUsers(): bool
    {
        return $this->isActive() && $this->isSystemOwner();
    }

    /**
     * Determine whether the user can manage municipal staff.
     */
    public function canManageMunicipalStaff(): bool
    {
        return $this->isActive()
            && ($this->canOverseeSystem()
                || ($this->isMunicipalHead() && $this->municipality_id !== null));
    }

    /**
     * Determine whether the user can modify operational records.
     *
     * Super administrators provide province-wide oversight and manage user
     * accounts, but operational data entry belongs to agriculture staff.
     */
    public function canManageOperationalData(): bool
    {
        return $this->isActive()
            && $this->hasAnyRole([
                self::ROLE_PROVINCIAL_STAFF,
                self::ROLE_MUNICIPAL_HEAD,
                self::ROLE_MUNICIPAL_STAFF,
            ]);
    }

    /**
     * Audit records contain province-wide security and change information.
     */
    public function canViewAuditTrail(): bool
    {
        return $this->canOverseeSystem() && $this->hasUsableScope();
    }

    /**
     * Determine whether the user requires an assigned municipality.
     */
    public function requiresMunicipality(): bool
    {
        return $this->isMunicipalUser();
    }

    /**
     * Return a readable role label.
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SYSTEM_OWNER => 'System Owner',
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_PROVINCIAL_STAFF => 'Provincial Staff',
            self::ROLE_PROVINCIAL_VET => 'Provincial Veterinary Office',
            self::ROLE_MUNICIPAL_HEAD => 'Head Agriculturist',
            self::ROLE_MUNICIPAL_STAFF => 'Municipal Staff',
            default => 'Unknown Role',
        };
    }

    /**
     * Return the user's office label.
     */
    public function getOfficeLabelAttribute(): string
    {
        if ($this->isSystemOwner()) {
            return 'System Administration';
        }
        if ($this->isProvincialVeterinaryOffice()) {
            return 'Provincial Veterinary Office';
        }

        if ($this->isProvincialUser()) {
            return 'Provincial Agriculture Office';
        }

        if ($this->municipality) {
            return $this->municipality->name.' Municipal Agriculture Office';
        }

        return 'Municipality Not Assigned';
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Only retrieve active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Only retrieve provincial-level users.
     */
    public function scopeProvincialUsers(Builder $query): Builder
    {
        return $query->whereIn('role', self::PROVINCIAL_ROLES);
    }

    /**
     * Only retrieve municipality-level users.
     */
    public function scopeMunicipalUsers(Builder $query): Builder
    {
        return $query->whereIn('role', self::MUNICIPAL_ROLES);
    }

    /**
     * Retrieve users assigned to a particular municipality.
     */
    public function scopeForMunicipality(
        Builder $query,
        int $municipalityId
    ): Builder {
        return $query->where('municipality_id', $municipalityId);
    }

    /**
     * Retrieve municipal heads.
     */
    public function scopeMunicipalHeads(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_MUNICIPAL_HEAD);
    }

    /**
     * Retrieve municipal staff.
     */
    public function scopeMunicipalStaff(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_MUNICIPAL_STAFF);
    }
}
