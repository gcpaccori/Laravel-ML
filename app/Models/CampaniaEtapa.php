<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaniaEtapa extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['fecha_inicio_formato', 'fecha_fin_formato'];

    public function campaniaEspecie() : BelongsTo
    {
        return $this->belongsTo(CampaniaEspecie::class);
    }

    public function etapa() : BelongsTo
    {
        return $this->belongsTo(Etapa::class);
    }

    public function piscina() : belongsTo
    {
        return $this->belongsTo(Piscina::class);
    }

    public function parametrosProduccion()
    {
        return $this->hasOne(ParametrosProduccion::class);
    }

    /**
     * Relación: Una etapa puede tener muchas biometrias
     */
    public function biometrias() : HasMany
    {
        return $this->hasMany(Biometria::class);
    }

    protected function casts(): array
    {
        return [
            "fecha_inicio"           => 'date',
            "fecha_fin"              => 'date',
            "area_piscigranja_m2"    => 'float',
            "volumen_piscigranja_m3" => 'float',
            "altura_piscigranja_m"   => 'float',
            "numero_peces_inicial"   => 'integer',
            "numero_peces_final"     => 'integer',
            "peso_inicial_gr"        => 'float',
            "peso_final_gr"          => 'float',
            "densidad_siembra"       => 'float'
        ];
    }

    protected function fechaInicioFormato(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->fecha_inicio?->format('d/m/Y') ?? '-';
            }
        );
    }

    protected function fechaFinFormato(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->fecha_fin?->format('d/m/Y') ?? '-';
            }
        );
    }
}
