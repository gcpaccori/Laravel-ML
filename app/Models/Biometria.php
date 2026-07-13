<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Biometria extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $appends = ['nombre_etapa', 'nombre_piscina', 'nombre_especie', 'nombre_campania', 'nombre_piscigranja', 'ultimo_registro'];

    protected function casts(): array
    {
        return [
            'fecha_inicial'                 => 'date',
            'fecha_muestreo'                => 'date',
            'cantidad_muestreo'             => 'integer',
            'tiempo_dias'                   => 'integer',
            'muestreo_porcentaje'           => 'float',
            'cantidad_peces_iniciales'      => 'float',
            'cantidad_peces_actuales'       => 'float',
            'bi_kg'                         => 'float',
            'bf_kg'                         => 'float',
            'prom_longitud_cm'              => 'float',
            'prom_peso_g'                   => 'float',
            'tasa_crecimiento_g_dia'        => 'float',
            'total_alimento_consumido_kg'   => 'float',
            'conversion_alimenticia'        => 'float',
            'tasa_supervivencia_porcentaje' => 'float',
        ];
    }

    // ==================== RELACIONES ====================

    public function campaniaEtapa(): BelongsTo
    {
        return $this->belongsTo(CampaniaEtapa::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(BiometriaDetalle::class);
    }

    // ==================== CADENA ====================

    /**
     * Biometría anterior en la cadena (misma etapa, fecha_muestreo/id menor).
     */
    public function anterior(): ?self
    {
        return static::where('campania_etapa_id', $this->campania_etapa_id)
            ->where(function ($q) {
                $q->where('fecha_muestreo', '<', $this->fecha_muestreo)
                  ->orWhere(function ($q2) {
                      $q2->where('fecha_muestreo', $this->fecha_muestreo)
                         ->where('id', '<', $this->id ?? PHP_INT_MAX);
                  });
            })
            ->orderByDesc('fecha_muestreo')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Biometría siguiente en la cadena.
     */
    public function siguiente(): ?self
    {
        return static::where('campania_etapa_id', $this->campania_etapa_id)
            ->where(function ($q) {
                $q->where('fecha_muestreo', '>', $this->fecha_muestreo)
                  ->orWhere(function ($q2) {
                      $q2->where('fecha_muestreo', $this->fecha_muestreo)
                         ->where('id', '>', $this->id);
                  });
            })
            ->orderBy('fecha_muestreo')
            ->orderBy('id')
            ->first();
    }

    /**
     * true si este registro es el último de la cadena
     * (por lo tanto, editable/eliminable).
     */
    public function esUltimoRegistro(): bool
    {
        return $this->siguiente() === null;
    }

    public function esPrimerRegistro(): bool
    {
        return $this->anterior() === null;
    }

    // ==================== NOMBRES (ya existentes) ====================

    protected function nombreEtapa(): Attribute
    {
        return Attribute::make(get: fn () => $this->campaniaEtapa?->etapa?->nombre ?? '-');
    }

    protected function nombrePiscina(): Attribute
    {
        return Attribute::make(get: fn () => $this->campaniaEtapa?->piscina?->nombre ?? '-');
    }

    protected function nombreEspecie(): Attribute
    {
        return Attribute::make(get: fn () => $this->campaniaEtapa?->campaniaEspecie?->especie?->nombre ?? '-');
    }

    protected function nombreCampania(): Attribute
    {
        return Attribute::make(get: fn () => $this->campaniaEtapa?->campaniaEspecie?->campania?->nombre ?? '-');
    }

    protected function nombrePiscigranja(): Attribute
    {
        return Attribute::make(get: fn () => $this->campaniaEtapa?->campaniaEspecie?->campania?->piscigranja?->nombre ?? '-');
    }

    protected function ultimoRegistro(): Attribute
    {
        return Attribute::make(get: fn () => $this->esUltimoRegistro());
    }
}
