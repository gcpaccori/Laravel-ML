<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Modulo extends Model
{
    use SoftDeletes;
    protected $guarded = [];

    public function sistema(): BelongsTo
    {
        return $this->belongsTo(Sistema::class);
    }

    public function acciones() : BelongsToMany
    {
        return $this->belongsToMany(Accion::class, 'accion_modulo', 'modulo_id', 'accion_id');
    }

    public function children() : HasMany
    {
        return $this->hasMany(Modulo::class, 'cod_father');
    }

    public function parent() : BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'cod_father');
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean'
        ];
    }
}
