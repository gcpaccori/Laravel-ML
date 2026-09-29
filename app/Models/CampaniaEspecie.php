<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaniaEspecie extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['fecha_siembra_formateada'];

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

    public function alimentacionTabla(): HasOne
    {
        return $this->hasOne(AlimentacionTabla::class);
    }

    protected function casts(): array
    {
        return [
            'peso_inicial_gr'    => 'float',
            'peso_final_gr'      => 'float',
            'cantidad_siembra'   => 'integer',
            'cantidad_cosechada' => 'integer',
            'fecha_siembra'      => 'date',
        ];
    }

    protected function fechaSiembraFormateada(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->fecha_siembra?->format('d/m/Y') ?? '-';
            }
        );
    }
}
