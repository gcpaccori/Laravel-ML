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
            'piscigranja_id'              => ['required'],
            'campania_id'                 => ['required'],
            'campania_especie_id'         => ['required'],
            'campania_etapa_id'           => ['required', 'exists:campania_etapas,id'],
            'fecha_muestreo'              => ['required', 'date'],
            'cantidad_muestreo'           => ['required', 'integer', 'min:1'],
            'cantidad_peces_actuales'     => ['required', 'integer', 'min:1'],
            'total_alimento_consumido_kg' => ['required', 'numeric', 'min:0.1'],
            'observaciones'               => ['nullable', 'string'],
            'detalles'                    => ['required', 'array', 'min:1'],
            'detalles.*.longitud_cm'      => ['required', 'numeric'],
            'detalles.*.peso_g'           => ['required', 'numeric'],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'piscigranja_id.required'              => 'Debe seleccionar una piscigranja.',
            'campania_id.required'                 => 'Debe seleccionar una campaña.',
            'campania_especie_id.required'         => 'Debe seleccionar una especie.',
            'campania_etapa_id.required'           => 'Debe seleccionar una etapa.',
            'campania_etapa_id.exists'             => 'La etapa seleccionada no es válida.',
            'fecha_muestreo.required'              => 'La fecha de muestreo es obligatoria.',
            'fecha_muestreo.date'                  => 'La fecha de muestreo no tiene un formato válido.',
            'cantidad_muestreo.required'           => 'La cantidad de muestreo es obligatoria.',
            'cantidad_muestreo.integer'            => 'La cantidad de muestreo debe ser un número entero.',
            'cantidad_muestreo.min'                => 'La cantidad de muestreo debe ser mayor que cero.',
            'cantidad_peces_actuales.required'     => 'La cantidad de peces actuales es obligatoria.',
            'cantidad_peces_actuales.integer'      => 'La cantidad de peces actuales debe ser un número entero.',
            'cantidad_peces_actuales.min'          => 'La cantidad de peces actuales debe ser mayor que cero.',
            'total_alimento_consumido_kg.required' => 'La cantidad de alimento es obligatoria.',
            'total_alimento_consumido_kg.numeric'  => 'La cantidad de alimento debe ser un número.',
            'total_alimento_consumido_kg.min'      => 'La cantidad de alimento debe ser mayor que cero.',
            'observaciones.string'                 => 'Las observaciones deben ser un texto válido.',
            'detalles.required'                    => 'Debe registrar al menos un detalle de biometría.',
            'detalles.array'                       => 'El detalle de biometría debe ser una lista válida.',
            'detalles.min'                         => 'Debe registrar al menos un detalle de biometría.',
            'detalles.*.peso_g.required'           => 'El peso es obligatorio.',
            'detalles.*.peso_g.numeric'            => 'El peso debe ser un número.',
            'detalles.*.longitud_cm.required'      => 'La talla es obligatoria.',
            'detalles.*.longitud_cm.numeric'       => 'La talla debe ser un número.',
        ];
    }
}
