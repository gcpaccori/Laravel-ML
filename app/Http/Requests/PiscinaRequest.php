<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PiscinaRequest extends FormRequest
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
            "piscigranja_id" => 'required|integer',
            'nombre'  => [
                'required',
                'string',
                'max:100',
                Rule::unique('piscinas')
                    ->ignore($this->id) // para edición
                    ->where(fn ($query) =>
                        $query->where('piscigranja_id', $this->piscigranja_id)
                              ->whereNull('deleted_at')
                ),
            ],
            "descripcion" => 'nullable',
            "superficie_m2" => 'nullable|decimal:0,2',
            "profundidad_m" => 'nullable|decimal:0,2',
            "volumen_m3" => 'nullable|decimal:0,2',
            "estado" => 'required|string',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'piscigranja_id.required' => 'Debe seleccionar una piscigranja.',
            'piscigranja_id.integer'  => 'El campo piscigranja no es válido.',

            'nombre.required' => 'El nombre de la piscina es obligatorio.',
            'nombre.string'   => 'El nombre de la piscina debe ser texto.',
            'nombre.max'      => 'El nombre de la piscina no puede superar los 100 caracteres.',
            'nombre.unique'   => 'Ya existe una piscina con este nombre en la piscigranja seleccionada.',

            'descripcion.string' => 'La descripción debe ser texto.',

            'superficie_m2.decimal' => 'La superficie debe ser un número con hasta 2 decimales.',
            'profundidad_m.decimal' => 'La profundidad debe ser un número con hasta 2 decimales.',
            'volumen_m3.decimal' => 'El volumen debe ser un número con hasta 2 decimales.',

            'estado.required' => 'El estado es obligatorio.',
            'estado.string'   => 'El estado debe ser texto.',
        ];
    }
}
