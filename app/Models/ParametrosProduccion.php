<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParametrosProduccion extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            "dias_alimentacion"          => 'integer',
            "dias_muestreo"              => 'integer',
            "numero_muestreos"           => 'integer',
            "cantidad_alimento_total_kg" => 'float',
            "racion_diaria_gr"           => 'float',
            "frecuencia_diaria"          => 'float', // Número de veces
            "cantidad_por_frecuencia_gr" => 'float',
        ];
    }

    public function campaniaEtapa()
    {
        return $this->belongsTo(CampaniaEtapa::class);
    }
}
