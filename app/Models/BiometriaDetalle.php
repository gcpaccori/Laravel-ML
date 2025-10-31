<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometriaDetalle extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function biometria() : BelongsTo
    {
        return $this->belongsTo(Biometria::class);
    }

    protected function casts(): array
    {
        return [
            "fecha_registro" => 'date',
            "numero_muestra" => 'integer',
            "tamanio_cm"     => 'float',
            "peso_gr"        => 'float',
        ];
    }
}





