<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AgriculturalMachinery extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'tractor' => 'Four-wheel tractor',
        'hand_tractor' => 'Hand tractor / power tiller',
        'combine_harvester' => 'Combine harvester',
        'transplanter' => 'Rice transplanter',
        'thresher' => 'Thresher',
        'dryer' => 'Mechanical dryer',
        'milling_machine' => 'Milling machine',
        'irrigation_pump' => 'Irrigation pump',
        'sprayer' => 'Power sprayer',
        'drone' => 'Agricultural drone',
        'other' => 'Other machinery',
    ];

    public const CONDITIONS = [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'fair' => 'Fair',
        'needs_repair' => 'Needs repair',
        'unserviceable' => 'Unserviceable',
    ];

    public const AVAILABILITY_STATUSES = [
        'available' => 'Available',
        'in_use' => 'In use',
        'maintenance' => 'Under maintenance',
        'inactive' => 'Inactive',
    ];

    public const ACQUISITION_SOURCES = [
        'municipal_purchase' => 'Municipal purchase',
        'government_grant' => 'Government grant',
        'donation' => 'Donation',
        'cooperative_purchase' => 'Cooperative purchase',
        'farmer_owned' => 'Farmer-owned',
        'leased' => 'Leased',
        'other' => 'Other source',
    ];

    protected $fillable = [
        'municipality_id',
        'farmer_id',
        'farmers_cooperative_id',
        'asset_code',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'year_acquired',
        'acquisition_date',
        'acquisition_source',
        'acquisition_cost',
        'condition_status',
        'availability_status',
        'location',
        'service_hours',
        'last_maintenance_date',
        'next_maintenance_date',
        'notes',
    ];

    protected $casts = [
        'municipality_id' => 'integer',
        'farmer_id' => 'integer',
        'farmers_cooperative_id' => 'integer',
        'year_acquired' => 'integer',
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'service_hours' => 'decimal:2',
        'last_maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
    ];

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(
            FarmersCooperative::class,
            'farmers_cooperative_id'
        );
    }

    public function scopeNeedsMaintenanceAttention(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('availability_status', 'maintenance')
                ->orWhereIn('condition_status', [
                    'needs_repair',
                    'unserviceable',
                ])
                ->orWhere(function (Builder $query) {
                    $query->whereNotNull('next_maintenance_date')
                        ->whereDate(
                            'next_maintenance_date',
                            '<=',
                            now()->addDays(30)->toDateString()
                        );
                });
        });
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst(
            str_replace('_', ' ', (string) $this->category)
        );
    }

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition_status] ?? ucfirst(
            str_replace('_', ' ', (string) $this->condition_status)
        );
    }

    public function getAvailabilityLabelAttribute(): string
    {
        return self::AVAILABILITY_STATUSES[$this->availability_status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->availability_status));
    }

    public function getAcquisitionSourceLabelAttribute(): string
    {
        return self::ACQUISITION_SOURCES[$this->acquisition_source]
            ?? ucfirst(str_replace('_', ' ', (string) $this->acquisition_source));
    }

    public function getHolderTypeAttribute(): string
    {
        if ($this->farmer_id) {
            return 'farmer';
        }

        if ($this->farmers_cooperative_id) {
            return 'cooperative';
        }

        return 'unassigned';
    }

    public function getHolderLabelAttribute(): string
    {
        if ($this->farmer) {
            return trim(implode(' ', array_filter([
                $this->farmer->first_name,
                $this->farmer->middle_name,
                $this->farmer->last_name,
                $this->farmer->ext_name,
            ])));
        }

        return $this->cooperative?->name ?? 'Unassigned';
    }

    public function getMaintenanceNeedsAttentionAttribute(): bool
    {
        if (
            $this->availability_status === 'maintenance'
            || in_array(
                $this->condition_status,
                ['needs_repair', 'unserviceable'],
                true
            )
        ) {
            return true;
        }

        return $this->next_maintenance_date instanceof Carbon
            && $this->next_maintenance_date->lte(now()->addDays(30));
    }
}
