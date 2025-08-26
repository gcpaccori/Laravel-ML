<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PiscigranjaRequest extends FormRequest
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
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique('piscigranjas')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'departamento_id' => 'required|string|max:2',
            'provincia_id' => 'required|string|max:4',
            'distrito_id' => 'required|string|max:6',
            'direccion' => 'nullable|string|max:255',
            'latitud' => 'nullable|decimal:1,8|numeric',
            'longitud' => 'nullable|decimal:1,8|numeric',
            'descripcion' => 'nullable|string|max:255',
            'propietario' => 'nullable|string|max:255',
            'telefono_contacto' => 'nullable|string|max:20',
            'email_contacto' => 'nullable|email|max:100',
            'activo' => 'required|boolean',

            // --- reglas para piscinas ---
            'piscinas'                  => 'nullable|array',
            'piscinas.*.nombre'         => 'required|string|max:100',
            'piscinas.*.descripcion'    => 'nullable|string',
            'piscinas.*.superficie_m2'  => 'nullable|numeric|min:0|max:999999.99',
            'piscinas.*.profundidad_m'  => 'nullable|numeric|min:0|max:999.99',
            'piscinas.*.estado'         => 'required|string|in:operativa,mantenimiento,inactiva',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la piscigranja es obligatorio.',
            'nombre.string' => 'El nombre debe ser un texto válido.',
            'nombre.max' => 'El nombre no debe superar los 150 caracteres.',
            'nombre.unique' => 'Ya existe una piscigranja registrada con este nombre.',

            'departamento_id.required' => 'El departamento es obligatorio.',
            'departamento_id.string' => 'El departamento debe ser un texto válido.',
            'departamento_id.max' => 'El departamento no debe superar los 2 caracteres.',

            'provincia_id.required' => 'La provincia es obligatoria.',
            'provincia_id.string' => 'La provincia debe ser un texto válido.',
            'provincia_id.max' => 'La provincia no debe superar los 4 caracteres.',

            'distrito_id.required' => 'El distrito es obligatorio.',
            'distrito_id.string' => 'El distrito debe ser un texto válido.',
            'distrito_id.max' => 'El distrito no debe superar los 6 caracteres.',

            'direccion.string' => 'La dirección debe ser un texto válido.',
            'direccion.max' => 'La dirección no debe superar los 255 caracteres.',

            'latitud.decimal' => 'La latitud debe tener entre 1 y 8 decimales.',
            'latitud.numeric' => 'La latitud debe ser un número válido.',

            'longitud.decimal' => 'La longitud debe tener entre 1 y 8 decimales.',
            'longitud.numeric' => 'La longitud debe ser un número válido.',

            'descripcion.string' => 'La descripción debe ser un texto válido.',
            'descripcion.max' => 'La descripción no debe superar los 255 caracteres.',

            'propietario.string' => 'El propietario debe ser un texto válido.',
            'propietario.max' => 'El propietario no debe superar los 255 caracteres.',

            'telefono_contacto.string' => 'El teléfono de contacto debe ser un texto válido.',
            'telefono_contacto.max' => 'El teléfono de contacto no debe superar los 20 caracteres.',

            'email_contacto.email' => 'El email de contacto debe ser un email válido.',
            'email_contacto.max' => 'El email de contacto no debe superar los 100 caracteres.',
            'email_contacto.email' => 'El email de contacto debe ser una dirección válida.',

            'activo.required' => 'El estado activo es obligatorio.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',

            // Piscinas
            'piscinas.array' => 'El formato no válido.',

            'piscinas.*.nombre.required' => 'El nombre es obligatorio.',
            'piscinas.*.nombre.string'   => 'El nombre debe ser un texto.',
            'piscinas.*.nombre.max'      => 'El nombre no puede superar los 100 caracteres.',

            'piscinas.*.descripcion.string' => 'La descripción debe ser un texto válido.',

            'piscinas.*.superficie_m2.numeric' => 'La superficie debe ser un valor numérico.',
            'piscinas.*.superficie_m2.min'     => 'La superficie no puede ser negativa.',
            'piscinas.*.superficie_m2.max'     => 'La superficie no puede superar los 999,999.99 m².',

            'piscinas.*.profundidad_m.numeric' => 'La profundidad debe ser un valor numérico.',
            'piscinas.*.profundidad_m.min'     => 'La profundidad no puede ser negativa.',
            'piscinas.*.profundidad_m.max'     => 'La profundidad no puede superar los 999.99 m.',

            'piscinas.*.estado.required' => 'El estado es obligatorio.',
            'piscinas.*.estado.string'   => 'El estado debe ser un texto válido.',
            'piscinas.*.estado.in'       => 'El estado debe ser: operativa, mantenimiento o inactiva.',
        ];
    }
}
