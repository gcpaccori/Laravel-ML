<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiometriaRequest extends FormRequest
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
            'piscigranja_id'      => ['required'],
            'campania_id'         => ['required'],
            'campania_especie_id' => ['required'],

            'campania_etapa_id' => ['required', 'exists:campania_etapas,id'],
            'fecha_muestreo'    => ['required', 'date'],
            'numero_muestreo'   => ['nullable', 'integer', 'min:1'],

            // Cantidad de peces
            'cantidad_peces_inicial' => ['nullable', 'integer', 'min:0'],
            'cantidad_peces_final'   => ['nullable', 'integer', 'min:0'],

            // Peso
            'peso_inicial_gr' => ['nullable', 'numeric', 'min:0'],
            'peso_final_gr'   => ['nullable', 'numeric', 'min:0'],

            // Tamaño
            'tamanio_inicial_cm' => ['nullable', 'numeric', 'min:0'],
            'tamanio_final_cm'   => ['nullable', 'numeric', 'min:0'],

            // Biomasa
            'biomasa_inicial_kg' => ['nullable', 'numeric', 'min:0'],
            'biomasa_final_kg'   => ['nullable', 'numeric', 'min:0'],

            // Indicadores
            'tasa_supervivencia_porcentaje'          => ['nullable', 'numeric', 'between:0,100'],
            'tasa_crecimiento_especifico_porcentaje' => ['nullable', 'numeric', 'between:0,100'],

            'observaciones' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'piscigranja_id'                         => 'piscigranja',
            'campania_id'                            => 'campaña',
            'campania_especie_id'                    => 'especie',
            'campania_etapa_id'                      => 'etapa de campaña',
            'fecha_muestreo'                         => 'fecha de muestreo',
            'numero_muestreo'                        => 'número de muestreo',
            'cantidad_peces_inicial'                 => 'cantidad de peces inicial',
            'cantidad_peces_final'                   => 'cantidad de peces final',
            'peso_inicial_gr'                        => 'peso inicial (g)',
            'peso_final_gr'                          => 'peso final (g)',
            'tamanio_inicial_cm'                     => 'tamaño inicial (cm)',
            'tamanio_final_cm'                       => 'tamaño final (cm)',
            'biomasa_inicial_kg'                     => 'biomasa inicial (kg)',
            'biomasa_final_kg'                       => 'biomasa final (kg)',
            'tasa_supervivencia_porcentaje'          => 'tasa de supervivencia (%)',
            'tasa_crecimiento_especifico_porcentaje' => 'tasa de crecimiento específico (%)',
            'observaciones'                          => 'observaciones',
        ];
    }

    public function messages(): array
    {
        return [
            'piscigranja_id.required'      => 'La :attribute es obligatoria.',
            'campania_id.required'         => 'La :attribute es obligatoria.',
            'campania_especie_id.required' => 'La :attribute es obligatoria.',

            'campania_etapa_id.required' => 'La :attribute es obligatoria.',
            'campania_etapa_id.exists'   => 'La :attribute seleccionada no es válida.',

            'fecha_muestreo.required' => 'La :attribute es obligatoria.',
            'fecha_muestreo.date'     => 'La :attribute debe ser una fecha válida.',

            'numero_muestreo.integer' => 'El :attribute debe ser un número entero.',
            'numero_muestreo.min'     => 'El :attribute debe ser al menos 1.',

            'cantidad_peces_inicial.integer' => 'La :attribute debe ser un número entero.',
            'cantidad_peces_inicial.min'     => 'La :attribute no puede ser negativa.',
            'cantidad_peces_final.integer'   => 'La :attribute debe ser un número entero.',
            'cantidad_peces_final.min'       => 'La :attribute no puede ser negativa.',

            'peso_inicial_gr.numeric' => 'El :attribute debe ser un número válido.',
            'peso_inicial_gr.min'     => 'El :attribute no puede ser negativo.',
            'peso_final_gr.numeric'   => 'El :attribute debe ser un número válido.',
            'peso_final_gr.min'       => 'El :attribute no puede ser negativo.',

            'tamanio_inicial_cm.numeric' => 'El :attribute debe ser un número válido.',
            'tamanio_inicial_cm.min'     => 'El :attribute no puede ser negativo.',
            'tamanio_final_cm.numeric'   => 'El :attribute debe ser un número válido.',
            'tamanio_final_cm.min'       => 'El :attribute no puede ser negativo.',

            'biomasa_inicial_kg.numeric' => 'La :attribute debe ser un número válido.',
            'biomasa_inicial_kg.min'     => 'La :attribute no puede ser negativa.',
            'biomasa_final_kg.numeric'   => 'La :attribute debe ser un número válido.',
            'biomasa_final_kg.min'       => 'La :attribute no puede ser negativa.',

            'tasa_supervivencia_porcentaje.numeric'          => 'La :attribute debe ser un número válido.',
            'tasa_supervivencia_porcentaje.between'          => 'La :attribute debe estar entre 0 y 100.',
            'tasa_crecimiento_especifico_porcentaje.numeric' => 'La :attribute debe ser un número válido.',
            'tasa_crecimiento_especifico_porcentaje.between' => 'La :attribute debe estar entre 0 y 100.',

            'observaciones.string' => 'Las :attribute deben ser texto válido.',
        ];
    }
}
