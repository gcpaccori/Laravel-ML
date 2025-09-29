<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Campania extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function piscigranja() : BelongsTo
    {
        return $this->belongsTo(Piscigranja::class);
    }

    public function especies() : HasMany
    {
        return $this->hasMany(CampaniaEspecie::class);
    }

    /**
     * Relación: Obtener todas las biometrias a través de las etapas
     */
    // public function biometrias() : HasManyThrough
    // {
    //     return $this->hasManyThrough(Biometria::class, CampaniaEtapa::class);
    // }

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin_estimada' => 'date',
            'fecha_fin_real' => 'date',
        ];
    }
}
