<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class SistemaRequest extends FormRequest
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
            'name'  => [
                'required',
                'string',
                'max:255',
                Rule::unique('sistemas')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'icon'  => 'nullable|string|max:255',
            'url'   => [
                'nullable',
                'string',
                Rule::unique('sistemas')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'order' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'order.integer' => 'El orden debe ser un número entero.',
            'order.min'     => 'El orden debe ser mayor o igual a 0.',
            'name.unique'   => 'Este sistema ya existe.',
            'url.unique'    => 'Esta URL ya existe.',
        ];
    }
}
