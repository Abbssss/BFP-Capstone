<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $casts = [
        'start_date' => 'date',
        'completion_date' => 'date',
        'next_recommended_maintenance_date' => 'date',
    ];

    protected $fillable = [
        'equipment_id', 'request_number', 'maintenance_type', 'priority', 'status', 'reported_issue',
        'diagnosis', 'action_taken', 'parts_replaced', 'maintenance_cost', 'start_date', 'completion_date',
        'technician_name', 'assigned_to_id', 'downtime_hours', 'maintenance_outcome', 'next_recommended_maintenance_date',
        'supporting_document', 'notes', 'approval_status', 'approval_notes', 'reviewed_by_id', 'reviewed_at',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
