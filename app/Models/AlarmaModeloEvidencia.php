<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmaModeloEvidencia extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'prediction_for' => 'datetime',
            'predicted_value' => 'float',
            'evidence' => 'array',
        ];
    }

    public function alarma(): BelongsTo
    {
        return $this->belongsTo(Alarma::class);
    }
}
