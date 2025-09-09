<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use App\Models\CampaniaEtapa;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CampaniaEtapaCloseRequest extends FormRequest
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
        $campaniaEtapa = CampaniaEtapa::find($this->campania_etapa_id);

        return [
            "fecha_fin" => [
                'required',
                'date',
                function ($attribute, $value, $fail) use ($campaniaEtapa) {
                    if ($campaniaEtapa && $value <= $campaniaEtapa->fecha_inicio) {
                        $fail('La fecha fin no puede ser menor o igual a la fecha de inicio (' .
                            Carbon::parse($campaniaEtapa->fecha_inicio)->format('d/m/Y') . ').');
                    }
                }
            ],
            "cantidad_final" => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) use ($campaniaEtapa) {
                    if ($campaniaEtapa && $value > $campaniaEtapa->cantidad_inicial) {
                        $fail('La cantidad final no puede ser mayor a la cantidad inicial (' .$campaniaEtapa->cantidad_inicial. ').');
                    }
                }
            ],
            "peso_promedio_gr" => 'required|decimal:0,2|min:1'
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Mensajes para fecha_fin
            'fecha_fin.required' => 'La fecha fin es obligatoria.',
            'fecha_fin.date' => 'La fecha fin debe ser una fecha válida.',

            // Mensajes para cantidad_final
            'cantidad_final.required' => 'La cantidad final es obligatoria.',
            'cantidad_final.integer' => 'La cantidad final debe ser un número entero.',
            'cantidad_final.min' => 'La cantidad final debe ser mayor a 0.',

            // Mensajes para peso_promedio_gr
            'peso_promedio_gr.required' => 'El peso promedio es obligatorio.',
            'peso_promedio_gr.decimal' => 'El peso promedio debe tener máximo 2 decimales.',
            'peso_promedio_gr.min' => 'El peso promedio debe ser mayor a 0.',
        ];
    }
}
