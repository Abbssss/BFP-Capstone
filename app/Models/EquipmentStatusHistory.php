<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'equipment_id', 'old_status', 'new_status', 'changed_by', 'reason', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
