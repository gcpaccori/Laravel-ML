<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlimentacionBftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poblacion_inicial' => ['required', 'integer', 'min:1'],
            'mortalidad_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'numero_semanas' => ['required', 'integer', 'min:1', 'max:104'],
            'semanas_por_mes' => ['required', 'integer', 'min:1', 'max:8'],
            'observaciones' => ['nullable', 'string'],

            'horarios' => ['required', 'array', 'min:1'],
            'horarios.*.hora' => ['required', 'date_format:H:i'],

            'semanas' => ['required', 'array', 'min:1'],
            'semanas.*.numero_semana' => ['required', 'integer', 'min:1', 'distinct'],
            'semanas.*.ganancia_peso_g' => ['required', 'numeric', 'min:0'],
            'semanas.*.tasa_alimentacion_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],

            'meses' => ['required', 'array', 'min:1'],
            'meses.*.numero_mes' => ['required', 'integer', 'min:1', 'distinct'],
            'meses.*.tipo_alimento' => ['nullable', 'string', 'max:100'],

            'peso_inicial_gr' => ['required', 'numeric'],
            'peso_final_gr' => ['required', 'numeric'],

            'ta_inicial' => ['required', 'numeric'],
            'ta_final' => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'poblacion_inicial.required' => 'La población inicial es obligatoria.',
            'poblacion_inicial.integer' => 'La población inicial debe ser un número entero.',
            'poblacion_inicial.min' => 'La población inicial debe ser de al menos :min.',

            'mortalidad_porcentaje.required' => 'El porcentaje de mortalidad es obligatorio.',
            'mortalidad_porcentaje.numeric' => 'El porcentaje de mortalidad debe ser un número.',
            'mortalidad_porcentaje.min' => 'El porcentaje de mortalidad no puede ser menor a :min%.',
            'mortalidad_porcentaje.max' => 'El porcentaje de mortalidad no puede superar el :max%.',

            'numero_semanas.required' => 'El número de semanas es obligatorio.',
            'numero_semanas.integer' => 'El número de semanas debe ser un entero.',
            'numero_semanas.min' => 'Debe registrar al menos :min semana.',
            'numero_semanas.max' => 'El número de semanas no puede exceder :max.',

            'semanas_por_mes.required' => 'Las semanas por mes son obligatorias.',
            'semanas_por_mes.integer' => 'Las semanas por mes deben ser un entero.',
            'semanas_por_mes.min' => 'Debe ingresar al menos :min semana por mes.',
            'semanas_por_mes.max' => 'Las semanas por mes no pueden exceder de :max.',

            'observaciones.string' => 'Las observaciones deben ser un texto válido.',

            'peso_inicial_gr.required' => 'El peso inicial en gramos es obligatorio.',
            'peso_inicial_gr.numeric' => 'El peso inicial debe ser un valor numérico.',

            'peso_final_gr.required' => 'El peso final en gramos es obligatorio.',
            'peso_final_gr.numeric' => 'El peso final debe ser un valor numérico.',

            'ta_inicial.required' => 'La tasa de alimentación (TA) inicial es obligatoria.',
            'ta_inicial.numeric' => 'La tasa de alimentación (TA) inicial debe ser un número.',

            'ta_final.required' => 'La tasa de alimentación (TA) final es obligatoria.',
            'ta_final.numeric' => 'La tasa de alimentación (TA) final debe ser un número.',

            'horarios.required' => 'Debe registrar al menos un horario.',
            'horarios.array' => 'El formato de los horarios no es válido.',
            'horarios.min' => 'Debe agregar al menos :min horario.',
            'horarios.*.hora.required' => 'La hora es obligatoria.',
            'horarios.*.hora.date_format' => 'La hora debe tener un formato válido (HH:mm).',

            'semanas.required' => 'Debe registrar la información de las semanas.',
            'semanas.array' => 'El formato de las semanas no es válido.',
            'semanas.min' => 'Debe incluir al menos :min semana.',
            'semanas.*.numero_semana.required' => 'El número de semana es obligatorio.',
            'semanas.*.numero_semana.integer' => 'El número de semana debe ser un entero.',
            'semanas.*.numero_semana.min' => 'El número de semana debe ser al menos :min.',
            'semanas.*.numero_semana.distinct' => 'Hay números de semana repetidos.',
            'semanas.*.ganancia_peso_g.required' => 'La ganancia de peso es obligatoria.',
            'semanas.*.ganancia_peso_g.numeric' => 'La ganancia de peso debe ser un número.',
            'semanas.*.ganancia_peso_g.min' => 'La ganancia de peso no puede ser negativa.',
            'semanas.*.tasa_alimentacion_porcentaje.required' => 'La tasa de alimentación es obligatoria.',
            'semanas.*.tasa_alimentacion_porcentaje.numeric' => 'La tasa de alimentación debe ser un número.',
            'semanas.*.tasa_alimentacion_porcentaje.min' => 'La tasa de alimentación no puede ser menor a :min%.',
            'semanas.*.tasa_alimentacion_porcentaje.max' => 'La tasa de alimentación no puede superar el :max%.',

            'meses.required' => 'Debe registrar la información de los meses.',
            'meses.array' => 'El formato de los meses no es válido.',
            'meses.min' => 'Debe incluir al menos :min mes.',
            'meses.*.numero_mes.required' => 'El número de mes es obligatorio.',
            'meses.*.numero_mes.integer' => 'El número de mes debe ser un entero.',
            'meses.*.numero_mes.min' => 'El número de mes debe ser al menos :min.',
            'meses.*.numero_mes.distinct' => 'Hay números de mes repetidos.',
            'meses.*.tipo_alimento.string' => 'El tipo de alimento debe ser una cadena de texto.',
            'meses.*.tipo_alimento.max' => 'El tipo de alimento no puede exceder los :max caracteres.',
        ];
    }
}
