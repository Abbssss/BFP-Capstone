<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspection extends Model
{
    protected $casts = [
        'inspection_date' => 'datetime',
    ];

    protected $fillable = [
        'equipment_id', 'reference_number', 'inspection_date', 'inspector_name', 'inspection_type',
        'condition_observed', 'functional_test_result', 'defects_identified', 'safety_concerns',
        'recommended_action', 'notes', 'supporting_document', 'outcome', 'status',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
