<?php

namespace App\Models;

use App\Models\ParametroBanda;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ParametroAmbiente extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fecha_medicion'       => 'datetime',
        'fecha'                => 'date',
        'iluminancia'          => 'integer',
        'temperatura_ambiente' => 'float',
        'humedad_ambiente'     => 'float',
        'segundos_luz'         => 'integer',
        'segundos_oscuridad'   => 'integer',
    ];

    protected $appends = ['fotoperiodo', 'fecha_medicion_formato'];

    /**
     * Umbrales del sensor de luz (LUX):
     * < 1 => OSCURIDAD | 1-10 => TRANSICION | > 10 => LUZ
     */
    public static function calcularEstadoLuminico(float $iluminancia): string
    {
        $bandas = Cache::remember(
            'parametro_bandas_iluminancia',
            now()->addHours(24),
            fn () => ParametroBanda::query()
                ->where('parametro', 'iluminancia')
                ->orderBy('low_score')
                ->get()
        );

        $banda = $bandas->first(
            fn ($banda) =>
                $iluminancia >= $banda->low_score &&
                $iluminancia < $banda->high_score
        );

        return match ($banda?->title) {
            'Oscuridad'  => 'OSCURIDAD',
            'Transición' => 'TRANSICION',
            'Luz'        => 'LUZ',
            default      => 'DESCONOCIDO',
        };
    }

    /**
     * Registra una nueva lectura enviada por el LILYGO.
     * Calcula el estado lumínico y suma al contador diario los segundos
     * transcurridos desde la última lectura del mismo día, según el
     * estado en el que estaba esa última lectura (efecto "cronómetro").
     * Los segundos en TRANSICION no se contabilizan como L ni como D.
     */
    public static function registrarLectura(array $datos)
    {
        $fechaNow = now();
        $fecha = $fechaNow->toDateString();
        $ultima = self::where('piscina_id', $datos['piscina_id'])->whereDate('fecha', $fecha)->orderByDesc('created_at')->first();
        $segundosLuz = $ultima->segundos_luz ?? 0;
        $segundosOscuridad = $ultima->segundos_oscuridad ?? 0;

        if ($ultima) {
            $transcurridos = $ultima->fecha_medicion->diffInSeconds($fechaNow);

            if ($ultima->estado_luminico === 'LUZ') {
                $segundosLuz += $transcurridos;
            } elseif ($ultima->estado_luminico === 'OSCURIDAD') {
                $segundosOscuridad += $transcurridos;
            }
        }

        return self::create([
            'piscina_id'           => $datos['piscina_id'],
            // 'fecha_medicion'       => $datos['fecha_medicion'],
            'fecha_medicion'       => $fechaNow,
            'fecha'                => $fecha,
            'iluminancia'          => $datos['iluminancia'],
            'estado_luminico'      => self::calcularEstadoLuminico((float) $datos['iluminancia']),
            'temperatura_ambiente' => $datos['temperatura_ambiente'] ?? null,
            'humedad_ambiente'     => $datos['humedad_ambiente'] ?? null,
            'segundos_luz'         => $segundosLuz,
            'segundos_oscuridad'   => $segundosOscuridad,
        ]);
    }

    /**
     * Formato "13h 30m L : 10h 30m D" listo para mostrar en la vista.
     */
    protected function fotoperiodo(): Attribute
    {
        return Attribute::make(
            get: function () {
                return [
                    'horas_luz' => intdiv($this->segundos_luz, 3600),
                    'minutos_luz' => intdiv($this->segundos_luz % 3600, 60),
                    'horas_oscuridad' => intdiv($this->segundos_oscuridad, 3600),
                    'minutos_oscuridad' => intdiv($this->segundos_oscuridad % 3600, 60),
                    'formateado' => sprintf(
                        '%dh %dm L : %dh %dm D',
                        intdiv($this->segundos_luz, 3600),
                        intdiv($this->segundos_luz % 3600, 60),
                        intdiv($this->segundos_oscuridad, 3600),
                        intdiv($this->segundos_oscuridad % 3600, 60)
                    ),
                ];
            }
        );
    }

    protected function fechaMedicionFormato(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->fecha_medicion?->format('d/m/Y H:i:s') ?? '-';
                // return $this->created_at?->format('d/m/Y H:i:s') ?? '-';
            }
        );
    }

    public function piscina(): BelongsTo
    {
        return $this->belongsTo(Piscina::class);
    }
}
