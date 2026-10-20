<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskPrediction extends Model
{
    protected $fillable = [
        'equipment_id', 'model_version_id', 'prediction_label', 'confidence', 'prediction_timestamp', 'details_json',
    ];

    protected $casts = [
        'prediction_timestamp' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function modelVersion(): BelongsTo
    {
        return $this->belongsTo(ModelVersion::class);
    }
}
