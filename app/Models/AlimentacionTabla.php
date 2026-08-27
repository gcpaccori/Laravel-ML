<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlimentacionTabla extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mortalidad_porcentaje' => 'decimal:2',
            'calculado' => 'boolean',
        ];
    }

    public function campaniaEspecie()
    {
        return $this->belongsTo(CampaniaEspecie::class);
    }

    public function horarios()
    {
        return $this->hasMany(AlimentacionHorario::class)->orderBy('orden');
    }

    public function meses()
    {
        return $this->hasMany(AlimentacionMes::class)->orderBy('numero_mes');
    }

    public function semanas()
    {
        return $this->hasManyThrough(
            AlimentacionSemana::class,
            AlimentacionMes::class,
            // 'alimentacion_tabla_id', // FK en alimentacion_meses
            // 'alimentacion_mes_id',   // FK en alimentacion_semanas
        )->orderBy('numero_semana');
    }
}
