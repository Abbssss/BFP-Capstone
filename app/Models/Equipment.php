<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Equipment extends Model
{
    public const CATEGORIES = ['Mechanical', 'Non Mechanical'];

    protected $table = 'equipment';

    protected $fillable = [
        'asset_code', 'equipment_name', 'category', 'asset_tag', 'serial_number', 'manufacturer',
        'model', 'commission_date', 'service_age_years', 'location', 'assigned_personnel',
        'status', 'last_inspection_date', 'next_inspection_date', 'engine_operating_hours',
        'runtime_cycles', 'mechanical_failure_count', 'last_maintenance_date', 'maintenance_interval_days',
        'description', 'remarks', 'inspection_defects_count', 'last_inspection_outcome', 'office_code',
    ];

    protected $casts = [
        'commission_date' => 'date',
        'last_inspection_date' => 'date',
        'next_inspection_date' => 'date',
        'last_maintenance_date' => 'date',
    ];

    public function history(): HasMany
    {
        return $this->hasMany(EquipmentStatusHistory::class, 'equipment_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'equipment_id');
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(Inspection::class, 'equipment_id')->latestOfMany('inspection_date');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class, 'equipment_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_personnel');
    }

}
