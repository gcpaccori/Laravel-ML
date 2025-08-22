<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
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
                // Rule::unique('roles')->ignore($this->id)->where(function ($query) {
                //     $query->whereNull('deleted_at');
                // })
                Rule::unique('roles')->ignore($this->id)
            ],
            'active' => 'boolean',
            'permisos' => 'required|array',
            'permisos.*' => 'string'
        ];
    }

    public function messages()
    {
        return [
            'name.required'         => 'El nombre es obligatorio.',
            'name.unique'           => 'Ya existe este rol.',
            'permisos.required'     => 'Los permisos son obligatorios.',
        ];
    }
}
