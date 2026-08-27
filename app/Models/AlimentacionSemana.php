<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlimentacionSemana extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ganancia_peso_g'              => 'decimal:3',
            'tasa_alimentacion_porcentaje' => 'decimal:2',
            'poblacion_calculada'          => 'decimal:2',
            'biomasa_kg'                   => 'decimal:3',
            'consumo_diario_kg'            => 'decimal:4',
            'consumo_semanal_kg'           => 'decimal:3',
        ];
    }

    public function mes()
    {
        return $this->belongsTo(AlimentacionMes::class);
    }
}
