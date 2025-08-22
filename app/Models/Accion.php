<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Accion extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'accion_modulo', 'accion_id', 'modulo_id');
    }


    protected function casts(): array
    {
        return [
            'active' => 'boolean'
        ];
    }
}
