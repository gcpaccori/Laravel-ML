<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function casts(): array
    {
        return [
            "fecha_inicio" => 'date',
            "fecha_fin" => 'date',
            "cantidad_inicial" => 'integer',
            "cantidad_final" => 'integer',
            "peso_promedio_gr" => 'float',
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
