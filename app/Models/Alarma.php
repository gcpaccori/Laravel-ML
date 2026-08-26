<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Alarma extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valor_detectado' => 'float',
            'reconocida_en' => 'datetime',
            'resuelta_en' => 'datetime',
        ];
    }

    public function piscigranja(): BelongsTo
    {
        return $this->belongsTo(Piscigranja::class);
    }

    public function piscina(): BelongsTo
    {
        return $this->belongsTo(Piscina::class);
    }

    public function reconocidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconocida_por');
    }

    public function evidenciaModelo(): HasOne
    {
        return $this->hasOne(AlarmaModeloEvidencia::class);
    }
}
