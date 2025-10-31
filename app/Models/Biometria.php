<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Biometria extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['nombre_etapa', 'nombre_piscina', 'nombre_especie', 'nombre_campania', 'nombre_piscigranja'];

    public function getNombreEtapaAttribute()
    {
        return $this->campaniaEtapa?->etapa?->nombre ?? '-';
    }

    public function getNombrePiscinaAttribute()
    {
        return $this->campaniaEtapa?->piscina?->nombre ?? '-';
    }

    public function getNombreEspecieAttribute()
    {
        return $this->campaniaEtapa?->campaniaEspecie?->especie?->nombre ?? '-';
    }

    public function getNombreCampaniaAttribute()
    {
        return $this->campaniaEtapa?->campaniaEspecie?->campania?->nombre ?? '-';
    }

    public function getNombrePiscigranjaAttribute()
    {
        return $this->campaniaEtapa?->campaniaEspecie?->campania?->piscigranja?->nombre ?? '-';
    }

    /**
     * Relación: Una biometría pertenece a una campaña etapa
     */
    public function campaniaEtapa() : BelongsTo
    {
        return $this->belongsTo(CampaniaEtapa::class);
    }

    public function detalles() : HasMany
    {
        return $this->hasMany(BiometriaDetalle::class);
    }

    protected function casts(): array
    {
        return [
            "fecha_muestreo"                         => 'date',
            "cantidad_muestreo"                      => 'integer',
            "cantidad_peces_inicial"                 => 'integer',
            "cantidad_peces_final"                   => 'integer',
            "peso_inicial_gr"                        => 'float',
            "peso_final_gr"                          => 'float',
            "tamanio_inicial_cm"                     => 'float',
            "tamanio_final_cm"                       => 'float',
            "biomasa_inicial_kg"                     => 'float',
            "biomasa_final_kg"                       => 'float',
            "tasa_supervivencia_porcentaje"          => 'float',
            "tasa_crecimiento_especifico_porcentaje" => 'float',
        ];
    }

}
