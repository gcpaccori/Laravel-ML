<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParametroProduccionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'campania_etapa_id' => [
                'required',
                'integer',
                'exists:campania_etapas,id'
            ],
            'dias_alimentacion' => [
                'nullable',
                'integer',
                'min:1',
                'max:365'
            ],
            'dias_muestreo' => [
                'nullable',
                'integer',
                'min:1',
                'max:365'
            ],
            'numero_muestreos' => [
                'nullable',
                'integer',
                'min:1',
                'max:100'
            ],
            'cantidad_alimento_total_kg' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99'
            ],
            'racion_diaria_gr' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99'
            ],
            'frecuencia_diaria' => [
                'required',
                'integer',
                'min:1',
                'max:10'
            ],
            'cantidad_por_frecuencia_gr' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99'
            ],
            'foto_periodo_horas_dia'   => ['required', 'integer', 'min:0', 'max:24'],
            'foto_periodo_horas_noche' => ['required', 'integer', 'min:0', 'max:24'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'foto_periodo_horas_dia'     => 'Horas día',
            'foto_periodo_horas_noche'   => 'Horas noche',
            'campania_etapa_id'          => 'campaña etapa',
            'dias_alimentacion'          => 'días de alimentación',
            'dias_muestreo'              => 'días de muestreo',
            'numero_muestreos'           => 'número de muestreos',
            'cantidad_alimento_total_kg' => 'cantidad total de alimento (kg)',
            'racion_diaria_gr'           => 'ración diaria (gr)',
            'frecuencia_diaria'          => 'frecuencia diaria',
            'cantidad_por_frecuencia_gr' => 'cantidad por frecuencia (gr)'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto_periodo_horas_dia.required'   => 'La :attribute es obligatoria.',
            'foto_periodo_horas_noche.required' => 'La :attribute es obligatoria.',
            'campania_etapa_id.required'        => 'La :attribute es obligatoria.',
            'campania_etapa_id.exists'          => 'La :attribute seleccionada no existe.',
            'dias_alimentacion.min'             => 'Los :attribute deben ser al menos :min día.',
            'dias_alimentacion.max'             => 'Los :attribute no pueden ser más de :max días.',
            'dias_muestreo.min'                 => 'Los :attribute deben ser al menos :min día.',
            'dias_muestreo.max'                 => 'Los :attribute no pueden ser más de :max días.',
            'numero_muestreos.min'              => 'El :attribute debe ser al menos :min.',
            'numero_muestreos.max'              => 'El :attribute no puede ser más de :max.',
            'cantidad_alimento_total_kg.min'    => 'La :attribute debe ser mayor o igual a 0.',
            'cantidad_alimento_total_kg.max'    => 'La :attribute excede el límite permitido.',
            'racion_diaria_gr.min'              => 'La :attribute debe ser mayor o igual a 0.',
            'racion_diaria_gr.max'              => 'La :attribute excede el límite permitido.',
            'frecuencia_diaria.required'        => 'La :attribute es obligatoria.',
            'frecuencia_diaria.min'             => 'La :attribute debe ser al menos :min vez al día.',
            'frecuencia_diaria.max'             => 'La :attribute no puede ser más de :max veces al día.',
            'cantidad_por_frecuencia_gr.min'    => 'La :attribute debe ser mayor o igual a 0.',
            'cantidad_por_frecuencia_gr.max'    => 'La :attribute excede el límite permitido.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación: días de muestreo no debería ser mayor a días de alimentación
            if ($this->filled(['dias_alimentacion', 'dias_muestreo'])) {
                if ($this->dias_muestreo > $this->dias_alimentacion) {
                    $validator->errors()->add(
                        'dias_muestreo',
                        'Los días de muestreo no pueden ser mayores a los días de alimentación.'
                    );
                }
            }

            $dia = $this->input('foto_periodo_horas_dia');
            $noche = $this->input('foto_periodo_horas_noche');

            if (($dia + $noche) !== 24) {
                $validator->errors()->add('foto_periodo_horas_dia', 'La suma de horas de día y noche debe ser 24.');
                $validator->errors()->add('foto_periodo_horas_noche', 'La suma de horas de día y noche debe ser 24.');
            }

        });
    }
}
