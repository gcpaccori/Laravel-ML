<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaniaEspecie extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function campania() : BelongsTo
    {
        return $this->belongsTo(Campania::class);
    }

    public function especie() : BelongsTo
    {
        return $this->belongsTo(Especie::class);
    }

    public function etapas() : HasMany
    {
        return $this->hasMany(CampaniaEtapa::class);
    }

    protected function casts(): array
    {
        return [
            'peso_promedio_gr' => 'float',
            'cantidad_siembra' => 'integer',
            'cantidad_cosechada' => 'integer',
        ];
    }
}
