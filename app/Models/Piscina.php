<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function etapas() : HasMany
    {
        return $this->hasMany(CampaniaEtapa::class);
    }

    public function parametrosAguas() : HasMany
    {
        return $this->hasMany(ParametroAgua::class);
    }

    protected function casts(): array
    {
        return [
            'superficie_m2' => 'float',
            'profundidad_m' => 'float',
            'volumen_m3' => 'float',
        ];
    }
}
