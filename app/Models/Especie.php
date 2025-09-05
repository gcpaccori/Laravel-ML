<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Especie extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function campanias() : BelongsToMany
    {
        return $this->belongsToMany(Campania::class, 'campania_especies')
                    ->withPivot([
                        'id','cantidad_siembra','fecha_siembra',
                        'cantidad_cosechada','peso_promedio_gr','mortalidad_porcentaje',
                        'created_at','updated_at','deleted_at'
                    ])
                    ->using(CampaniaEspecie::class);
    }
}
