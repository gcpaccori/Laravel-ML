<?php

namespace App\Services;

use App\Events\AlarmaGenerada;
use App\Models\Alarma;
use App\Models\ParametroAgua;
use App\Models\ParametroAmbiente;
use App\Models\ParametroBanda;
use App\Models\Piscina;

class AlarmaParametrosService
{
    public function evaluarCalidadAgua(ParametroAgua $medicion): void
    {
        $parametros = ['temperatura', 'ph', 'oxigeno_disuelto', 'ion_nitrato'];

        foreach ($parametros as $parametro) {
            $this->evaluarParametro(
                medicion: $medicion,
                piscina: $medicion->piscina,
                parametro: $parametro,
                modulo: 'calidad_agua'
            );
        }
    }

    public function evaluarCalidadAmbiente(ParametroAmbiente $medicion): void
    {
        $parametros = [
            'temperatura_ambiente',
            'humedad_ambiente',
        ];

        foreach ($parametros as $parametro) {
            $this->evaluarParametro(
                medicion: $medicion,
                piscina: $medicion->piscina,
                parametro: $parametro,
                modulo: 'calidad_ambiente'
            );
        }
    }

    protected function evaluarParametro(
        object $medicion,
        Piscina $piscina,
        string $parametro,
        string $modulo
    ): void {
        $valor = $medicion->{$parametro};

        if ($valor === null) {
            return;
        }

        $banda = ParametroBanda::where('parametro', $parametro)
            ->where('alerta', true)
            ->where('nivel', '!=', 'normal')
            ->where('low_score', '<=', $valor)
            ->where('high_score', '>', $valor)
            ->first();

        if (!$banda) {
            return;
        }

        $this->generar(
            piscina: $piscina,
            parametro: $parametro,
            banda: $banda,
            valorDetectado: (float) $valor,
            modulo: $modulo
        );
    }

    protected function generar(
        Piscina $piscina,
        string $parametro,
        ParametroBanda $banda,
        float $valorDetectado,
        string $modulo
    ): void {
        $nombreParametro = ucfirst(
            str_replace('_', ' ', $parametro)
        );

        $titulo = sprintf(
            '%s en nivel %s',
            $nombreParametro,
            ucfirst($banda->nivel)
        );

        $mensaje = sprintf(
            '%s registrado de %s se encuentra dentro del rango "%s", considerado de nivel %s.',
            $nombreParametro,
            $valorDetectado,
            $banda->title,
            $banda->nivel
        );

        Alarma::create([
            'piscigranja_id'  => $piscina->piscigranja_id,
            'piscina_id'      => $piscina->id,
            'modulo'         => $modulo,
            'parametro'      => $parametro,
            'nivel'          => $banda->nivel,
            'valor_detectado' => $valorDetectado,
            'titulo'         => $titulo,
            'mensaje'        => $mensaje,
            'estado'         => 'activa',
        ]);

        event(new AlarmaGenerada);
    }
}
