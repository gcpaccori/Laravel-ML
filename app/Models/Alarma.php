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
            'valor_detectado' => 'decimal:3',
            'resuelta_en'     => 'datetime',
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

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por');
    }

    public const ESTADO_CONFIG = [
        'activa' => [
            'label' => 'Activa',
            'type' => 'danger',
        ],
        'resuelta' => [
            'label' => 'Resuelta',
            'type' => 'success',
        ],
    ];

    public const NIVEL_CONFIG = [
        'normal' => [
            'label' => 'Normal',
            'type' => 'success',
        ],
        'advertencia' => [
            'label' => 'Advertencia',
            'type' => 'warning',
        ],
        'critico' => [
            'label' => 'Crítico',
            'type' => 'danger',
        ],
        'emergencia' => [
            'label' => 'Emergencia',
            'type' => 'danger',
        ],
    ];

    public static function getEstado(string $estado): array
    {
        return self::ESTADO_CONFIG[$estado] ?? [
            'label' => ucfirst($estado),
            'type' => 'info',
        ];
    }

    public static function getNivel(string $nivel): array
    {
        return self::NIVEL_CONFIG[$nivel] ?? [
            'label' => ucfirst($nivel),
            'type' => 'info',
        ];
    }

    public function evidenciaModelo(): HasOne
    {
        return $this->hasOne(AlarmaModeloEvidencia::class);
    }
}
