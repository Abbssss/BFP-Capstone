<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelVersion extends Model
{
    protected $fillable = [
        'version_name', 'accuracy', 'precision', 'recall', 'f1_score', 'confusion_matrix', 'training_date', 'notes',
    ];

    protected $casts = [
        'training_date' => 'datetime',
    ];

    public function predictions(): HasMany
    {
        return $this->hasMany(RiskPrediction::class);
    }
}
