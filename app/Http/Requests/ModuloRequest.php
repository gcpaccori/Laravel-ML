<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ModuloRequest extends FormRequest
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
            'sistema_id' => 'required|integer',
            'name'  => [
                'required',
                'string',
                'max:255',
                Rule::unique('modulos')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'cod_father' => 'nullable|integer',
            'url'   => [
                'nullable',
                'string',
                Rule::requiredIf(filled($this->cod_father)),
                Rule::unique('modulos')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'icon'  => 'nullable|string|max:255',
            'order' => 'nullable|integer',
            'active' => 'required|boolean',
            // 'acciones' => 'array|min:1'
        ];
    }
}
