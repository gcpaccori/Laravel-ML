<?php

namespace App\Http\Requests;

use DB;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CampaniaEtapaRequest extends FormRequest
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
            "campania_especie_id" => 'required|integer',
            "etapa_id" => [
                'required',
                'integer',
                Rule::unique('campania_etapas')
                    ->where('campania_especie_id', $this->campania_especie_id)
                    ->where('piscina_id', $this->piscina_id)
                    ->ignore($this->id)->where(function ($query) {
                        $query->whereNull('deleted_at');
                    })
            ],
            "piscina_id" => 'required|integer',
            "fecha_inicio" => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    if ($this->campania_especie_id && $value) {
                        $campaniaEspecie = DB::table('campania_especies')
                            ->where('id', $this->campania_especie_id)
                            ->first();

                        if ($campaniaEspecie && $value < $campaniaEspecie->fecha_siembra) {
                            $fail('La fecha de inicio no puede ser anterior a la fecha de siembra (' .
                                  Carbon::parse($campaniaEspecie->fecha_siembra)->format('d/m/Y') . ').');
                        }
                    }
                }
            ],
            "fecha_fin" => 'nullable|date|after:fecha_inicio',
            "cantidad_inicial" => 'required|integer|min:1',
            "cantidad_final" => 'nullable|integer',
            "peso_promedio_gr" => 'nullable|decimal:0,2',
            "estado" => 'required|string',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campania_especie_id.required' => 'La campaña-especie es obligatoria.',
            'campania_especie_id.integer' => 'La campaña-especie debe ser un número entero.',

            'etapa_id.required' => 'La etapa es obligatoria.',
            'etapa_id.integer' => 'La etapa debe ser un número entero.',
            'etapa_id.unique' => 'Esta etapa ya esta registrada en esta piscina.',

            'piscina_id.required' => 'La piscina es obligatoria.',
            'piscina_id.integer' => 'La piscina debe ser un número entero.',

            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',

            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',

            'cantidad_inicial.required' => 'La cantidad inicial es obligatoria.',
            'cantidad_inicial.integer' => 'La cantidad inicial debe ser un número entero.',
            'cantidad_inicial.min' => 'La cantidad inicial debe ser al menos 1.',

            'cantidad_final.integer' => 'La cantidad final debe ser un número entero.',

            'peso_promedio_gr.decimal' => 'El peso promedio debe tener máximo 2 decimales.',

            'estado.required' => 'El estado es obligatorio.',
            'estado.string' => 'El estado debe ser un texto válido.',
        ];
    }
}
