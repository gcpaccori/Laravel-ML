<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAlimentacionBftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:150'],
            'responsable' => ['nullable', 'string', 'max:150'],

            'poblacion_inicial' => ['required', 'integer', 'min:1'],
            'mortalidad_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'numero_semanas' => ['required', 'integer', 'min:1', 'max:104'],
            'semanas_por_mes' => ['required', 'integer', 'min:1', 'max:8'],
            'observaciones' => ['nullable', 'string'],

            'horarios' => ['required', 'array', 'min:1'],
            'horarios.*.hora' => ['required', 'date_format:H:i'],

            'semanas' => ['required', 'array', 'min:1'],
            'semanas.*.numero_semana' => ['required', 'integer', 'min:1', 'distinct'],
            'semanas.*.ganancia_peso_g' => ['required', 'numeric', 'min:0'],
            'semanas.*.tasa_alimentacion_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],

            'meses' => ['required', 'array', 'min:1'],
            'meses.*.numero_mes' => ['required', 'integer', 'min:1', 'distinct'],
            'meses.*.tipo_alimento' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'semanas.*.numero_semana.distinct' => 'Hay números de semana repetidos.',
            'meses.*.numero_mes.distinct' => 'Hay números de mes repetidos.',
        ];
    }

    /**
     * Valida que la cantidad de semanas y meses enviados coincida
     * exactamente con numero_semanas / semanas_por_mes, y que no falten
     * semanas intermedias (1..N sin huecos).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $numeroSemanas = (int) $this->input('numero_semanas');
            $semanasPorMes = (int) $this->input('semanas_por_mes');
            $semanas = collect($this->input('semanas', []));
            $meses = collect($this->input('meses', []));

            if ($semanas->count() !== $numeroSemanas) {
                $validator->errors()->add(
                    'semanas',
                    "Se esperaban {$numeroSemanas} semanas, se recibieron {$semanas->count()}."
                );

                return;
            }

            $numerosRecibidos = $semanas->pluck('numero_semana')->map(fn ($n) => (int) $n)->sort()->values();
            $numerosEsperados = collect(range(1, $numeroSemanas));

            if ($numerosRecibidos->toArray() !== $numerosEsperados->toArray()) {
                $validator->errors()->add('semanas', 'Faltan semanas o hay números fuera de rango (deben ser 1..N sin huecos).');
            }

            $mesesEsperados = (int) ceil($numeroSemanas / max($semanasPorMes, 1));

            if ($meses->count() !== $mesesEsperados) {
                $validator->errors()->add(
                    'meses',
                    "Se esperaban {$mesesEsperados} meses según semanas_por_mes, se recibieron {$meses->count()}."
                );
            }
        });
    }
}
