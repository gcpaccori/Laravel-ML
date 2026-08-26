<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelAlertPolicy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'version' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function piscina(): BelongsTo
    {
        return $this->belongsTo(Piscina::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
