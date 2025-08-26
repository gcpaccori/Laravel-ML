<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Piscina extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    // Relación: Una piscina pertenece a una piscigranja
    public function piscigranja() : BelongsTo
    {
        return $this->belongsTo(Piscigranja::class);
    }

    public function etapas() : belongsToMany
    {
        return $this->belongsToMany(CampaniaEtapa::class, 'campania_etapa_piscinas');
    }

    protected function casts(): array
    {
        return [
            'superficie_m2' => 'float',
            'profundidad_m' => 'float',
        ];
    }
}
