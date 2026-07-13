<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometriaDetalle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'numero'      => 'integer',
            'peso_g'      => 'float',
            'longitud_cm' => 'float',
        ];
    }

    public function biometria(): BelongsTo
    {
        return $this->belongsTo(Biometria::class);
    }
}
