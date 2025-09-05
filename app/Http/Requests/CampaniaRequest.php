<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CampaniaRequest extends FormRequest
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
            "piscigranja_id"        => 'required|integer',
            'nombre'  => [
                'required',
                'string',
                'max:150',
                Rule::unique('campanias')
                    ->ignore($this->id) // para edición
                    ->where(fn ($query) =>
                        $query->where('piscigranja_id', $this->piscigranja_id)
                              ->whereNull('deleted_at')
                ),
            ],
            "fecha_inicio"          => 'required|date',
            "fecha_fin_estimada"    => 'nullable|date|after:fecha_inicio',
            "fecha_fin_real"        => 'nullable|date|after:fecha_inicio',
            "estado"                => 'required|string',

            // ESPECIE
            "especies"                         => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if (!is_array($value)) return;

                    $especieIds = array_column($value, 'especie_id');
                    $especieIds = array_filter($especieIds); // Remover valores null/vacíos

                    if (count($especieIds) !== count(array_unique($especieIds))) {
                        $fail('No se pueden repetir especies en la misma campaña.');
                    }
                }
            ],
            "especies.*.especie_id"            => 'required|integer',
            "especies.*.cantidad_siembra"      => 'required|integer',
            "especies.*.fecha_siembra"         => 'required|date|after_or_equal:fecha_inicio',
            "especies.*.cantidad_cosechada"    => 'nullable|integer',
            "especies.*.peso_promedio_gr"      => 'nullable|decimal:0,2'
        ];
    }

    public function messages(): array
    {
        return [
            // CAMPAÑA
            'piscigranja_id.required'        => 'La piscigranja es obligatoria.',
            'piscigranja_id.integer'         => 'La piscigranja debe ser un número válido.',

            'nombre.required'                => 'El nombre de la campaña es obligatorio.',
            'nombre.string'                  => 'El nombre debe ser un texto válido.',
            'nombre.max'                     => 'El nombre no puede exceder los 150 caracteres.',
            'nombre.unique'                  => 'Ya existe una campaña con este nombre en la piscigranja seleccionada.',

            'fecha_inicio.required'          => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'              => 'La fecha de inicio debe ser una fecha válida.',

            'fecha_fin_estimada.date'        => 'La fecha de fin estimada debe ser una fecha válida.',
            'fecha_fin_estimada.after'       => 'La fecha de fin estimada debe ser posterior a la fecha de inicio.',

            'fecha_fin_real.date'            => 'La fecha de fin real debe ser una fecha válida.',
            'fecha_fin_real.after'           => 'La fecha de fin real debe ser posterior a la fecha de inicio.',


            'estado.required'                => 'El estado de la campaña es obligatorio.',
            'estado.string'                  => 'El estado debe ser un texto válido.',

            // ESPECIES
            'especies.array'                 => 'Las especies deben ser enviadas como una lista.',

            'especies.*.especie_id.required' => 'La especie es obligatoria.',
            'especies.*.especie_id.integer'  => 'La especie debe ser un número válido.',

            'especies.*.cantidad_siembra.required' => 'La cantidad de siembra es obligatoria.',
            'especies.*.cantidad_siembra.integer'  => 'La cantidad de siembra debe ser un número entero.',

            'especies.*.fecha_siembra.required' => 'La fecha de siembra es obligatoria.',
            'especies.*.fecha_siembra.date'     => 'La fecha de siembra debe ser una fecha válida.',
            'especies.*.fecha_siembra.after_or_equal' => 'La fecha de siembra debe ser igual o posterior a la fecha de inicio de la campaña.',

            'especies.*.cantidad_cosechada.integer' => 'La cantidad cosechada debe ser un número entero.',

            'especies.*.peso_promedio_gr.decimal' => 'El peso promedio debe ser un número decimal válido.',
        ];
    }
}
