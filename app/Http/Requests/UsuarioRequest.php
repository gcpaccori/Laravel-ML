<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UsuarioRequest extends FormRequest
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
            'role_id' => 'required|integer',
            'name'  => [
                'required',
                'string',
                'max:255'
            ],
            'email'  => [
                'required',
                'email',
                Rule::unique('users')->ignore($this->id)->where(function ($query) {
                    $query->whereNull('deleted_at');
                })
            ],
            'password' => [
                'min:8',
                Rule::requiredIf(empty($this->id))
            ],
        ];
    }
}
