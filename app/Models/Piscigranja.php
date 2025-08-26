<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Piscigranja extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function piscinas() : HasMany
    {
        return $this->hasMany(Piscina::class);
    }

    public function campanias() : HasMany
    {
        return $this->hasMany(Campania::class);
    }

    // Departamento
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(UbigeoDepartamento::class, 'departamento_id');
    }

    // Provincia
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(UbigeoProvincia::class, 'provincia_id');
    }

    // Distrito
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(UbigeoDistrito::class, 'distrito_id');
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean'
        ];
    }
}
