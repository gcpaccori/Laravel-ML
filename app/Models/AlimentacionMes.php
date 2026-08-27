<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlimentacionMes extends Model
{

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consumo_mensual_kg' => 'decimal:3',
        ];
    }

    public function tabla()
    {
        return $this->belongsTo(AlimentacionTabla::class);
    }

    public function semanas()
    {
        return $this->hasMany(AlimentacionSemana::class)->orderBy('numero_semana');
    }
}
