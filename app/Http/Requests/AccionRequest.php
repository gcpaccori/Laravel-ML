<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AccionRequest extends FormRequest
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
            'accion'  => [
                'required',
                'string',
                'max:255',
                Rule::unique('accions')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'name'  => [
                'required',
                'string',
                'max:255',
                Rule::unique('accions')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'icon'  => 'required|string|max:255',
            'type'  => 'required|string|max:255',
            'location'  => 'required|string|max:1',
            'name_funcion'  => [
                'required',
                'string',
                'max:255',
                Rule::unique('accions')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],

        ];
    }

    public function messages()
    {
        return [
            'accion.required'       => 'La acción es obligatorio.',
            'name.required'         => 'El nombre es obligatorio.',
            'icon.required'         => 'El icono es obligatorio.',
            'type.required'         => 'El tipo es obligatorio.',
            'location.required'     => 'La locación es obligatorio.',
            'name_funcion.required' => 'El nombre de la función es obligatorio.',
            'accion.unique'         => 'Ya existe esta acción.',
            'name.unique'           => 'Ya existe este nombre.',
            'name_funcion.unique'   => 'Ya existe esta función.'
        ];
    }
}

