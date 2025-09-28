<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
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

        $campaniaId = $this->route('id')?->id;
        return [
            // Relaciones requeridas
            'campania_especie_id' => [
                'required',
                'integer',
                'exists:campania_especies,id'
            ],
            'etapa_id' => [
                'required',
                'integer',
                'exists:etapas,id',
                Rule::unique('campania_etapas')
                    ->where('campania_especie_id', $this->campania_especie_id)
                    ->where('piscina_id', $this->piscina_id)
                    ->ignore($campaniaId)
                    ->where(function ($query) {
                        $query->whereNull('deleted_at');
                    })
            ],
            'piscina_id' => [
                'required',
                'integer',
                'exists:piscinas,id',
                function ($attribute, $value, $fail) use ($campaniaId) {
                    if ($value) {
                        $piscinaOcupada = DB::table('campania_etapas')
                            ->where('piscina_id', $value)
                            ->whereNotIn('estado', ['finalizada', 'cancelada']) // 👈 solo bloquea si NO está finalizada ni cancelada
                            ->whereNull('deleted_at')
                            ->when($campaniaId, function ($query) use ($campaniaId) {
                                // Excluir el registro actual si es actualización
                                $query->where('id', '!=', $campaniaId);
                            })
                            ->exists();

                        if ($piscinaOcupada) {
                            $fail('La piscina seleccionada ya está siendo utilizada en otra etapa en proceso.');
                        }
                    }
                }
            ],

            // Medidas físicas de la piscigranja
            'area_piscigranja_m2' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99'
            ],
            'volumen_piscigranja_m3' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99'
            ],
            'altura_piscigranja_m' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999.99'
            ],

            // Fechas
            'fecha_inicio' => [
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
            'fecha_fin' => [
                'nullable',
                'date',
                'after:fecha_inicio'
            ],

            // Números de peces
            'numero_peces_inicial' => [
                'required',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->campania_especie_id && $value) {
                        $campaniaEspecie = DB::table('campania_especies')
                            ->where('id', $this->campania_especie_id)
                            ->first();

                        if ($campaniaEspecie && $value > $campaniaEspecie->cantidad_siembra) {
                            $fail('La cantidad inicial no puede ser mayor a la cantidad de siembra ('
                                . $campaniaEspecie->cantidad_siembra . ').');
                        }
                    }
                }
            ],
            'numero_peces_final' => [
                'nullable',
                'integer',
                'min:0',
                'lte:numero_peces_inicial'
            ],

            // Pesos
            'peso_inicial_gr' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                function ($attribute, $value, $fail) {
                    if ($this->campania_especie_id && $value) {
                        $campaniaEspecie = DB::table('campania_especies')
                            ->where('id', $this->campania_especie_id)
                            ->first();

                        if ($campaniaEspecie && $value > $campaniaEspecie->peso_inicial_gr) {
                            $fail('El peso inicial no puede ser menor al peso de siembra ('
                                . $campaniaEspecie->peso_inicial_gr . ').');
                        }
                    }
                }
            ],
            'peso_final_gr' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
                'gte:peso_inicial_gr'
            ],

            // Densidad de siembra
            'densidad_siembra' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999.9999'
            ],

            // Estado
            'estado' => [
                'required',
                'string',
                Rule::in(['planificada', 'en_proceso', 'finalizada', 'cancelada'])
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campania_especie_id.required' => 'La campaña especie es obligatoria.',
            'campania_especie_id.exists' => 'La campaña especie seleccionada no existe.',

            'etapa_id.required' => 'La etapa es obligatoria.',
            'etapa_id.exists' => 'La etapa seleccionada no existe.',
            'etapa_id.unique' => 'Esta etapa ya esta registrada en esta piscina.',

            'piscina_id.required' => 'La piscina es obligatoria.',
            'piscina_id.exists' => 'La piscina seleccionada no existe.',

            'area_piscigranja_m2.numeric' => 'El área debe ser un valor numérico.',
            'area_piscigranja_m2.min' => 'El área no puede ser negativa.',
            'area_piscigranja_m2.max' => 'El área no puede exceder 999,999.99 m².',

            'volumen_piscigranja_m3.numeric' => 'El volumen debe ser un valor numérico.',
            'volumen_piscigranja_m3.min' => 'El volumen no puede ser negativo.',
            'volumen_piscigranja_m3.max' => 'El volumen no puede exceder 999,999.99 m³.',

            'altura_piscigranja_m.numeric' => 'La altura debe ser un valor numérico.',
            'altura_piscigranja_m.min' => 'La altura no puede ser negativa.',
            'altura_piscigranja_m.max' => 'La altura no puede exceder 999.99 m.',

            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',

            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',

            'numero_peces_inicial.integer' => 'El número inicial de peces debe ser un número entero.',
            'numero_peces_inicial.min' => 'El número inicial de peces no puede ser negativo.',

            'numero_peces_final.integer' => 'El número final de peces debe ser un número entero.',
            'numero_peces_final.min' => 'El número final de peces no puede ser negativo.',
            'numero_peces_final.lte' => 'El número final de peces no puede ser mayor al inicial.',

            'peso_inicial_gr.numeric' => 'El peso inicial debe ser un valor numérico.',
            'peso_inicial_gr.min' => 'El peso inicial no puede ser negativo.',

            'peso_final_gr.numeric' => 'El peso final debe ser un valor numérico.',
            'peso_final_gr.min' => 'El peso final no puede ser negativo.',
            'peso_final_gr.gte' => 'El peso final no puede ser menor al peso inicial.',

            'densidad_siembra.numeric' => 'La densidad de siembra debe ser un valor numérico.',
            'densidad_siembra.min' => 'La densidad de siembra no puede ser negativa.',

            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser: planificada, en_proceso, finalizada o cancelada.'
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validación personalizada: si hay peso inicial y final, el final debe ser mayor o igual
            if ($this->peso_inicial_gr && $this->peso_final_gr) {
                if ($this->peso_final_gr < $this->peso_inicial_gr) {
                    $validator->errors()->add('peso_final_gr', 'El peso final debe ser mayor o igual al peso inicial.');
                }
            }

            // Validación personalizada: si hay número inicial y final de peces
            if ($this->numero_peces_inicial && $this->numero_peces_final) {
                if ($this->numero_peces_final > $this->numero_peces_inicial) {
                    $validator->errors()->add('numero_peces_final', 'El número final de peces no puede ser mayor al inicial (por mortalidad natural).');
                }
            }

            // Validación de coherencia entre medidas
            if ($this->area_piscigranja_m2 && $this->altura_piscigranja_m && $this->volumen_piscigranja_m3) {
                $volumenCalculado = $this->area_piscigranja_m2 * $this->altura_piscigranja_m;
                $diferencia = abs($volumenCalculado - $this->volumen_piscigranja_m3);

                if ($diferencia > 0.1) { // Tolerancia de 0.1 m³
                    $validator->errors()->add('volumen_piscigranja_m3', 'El volumen no coincide con el cálculo área × altura.');
                }
            }
        });
    }
}
