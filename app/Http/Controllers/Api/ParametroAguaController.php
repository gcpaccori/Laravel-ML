<?php

namespace App\Http\Controllers\Api;

use App\Events\ParametroAguaActualizado;
use App\Http\Controllers\Controller;
use App\Models\ParametroAgua;
use App\Models\ParametroAmbiente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Log;

class ParametroAguaController extends Controller
{

    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.monitoreo_api.api_key');
    }

    public function store(Request $request)
    {
        Log::info('Datos Recibidos', ['parametros' => $request->all()]);

        if (!hash_equals((string) $this->apiKey, (string) $request->api_key)) {
            return response()->json([
                'success' => false,
                'message' => 'API Key incorrecta.'
            ], 401);
        }

        try {
            $validated = $request->validate([
                'piscina_id'     => ['required', 'integer'],
                'fecha_medicion' => ['required', 'date'],

                // Parámetros del agua
                'temperatura'      => ['nullable', 'numeric'],
                'ph'               => ['nullable', 'numeric'],
                'oxigeno_disuelto' => ['nullable', 'numeric'],
                'ion_nitrato'      => ['nullable', 'numeric'],

                // Parámetros ambientales
                'iluminancia'          => ['nullable', 'numeric'],
                'temperatura_ambiente' => ['nullable', 'numeric'],
                'humedad_ambiente'     => ['nullable', 'numeric'],
            ]);

            $tieneParametrosAgua = collect(['temperatura', 'ph', 'oxigeno_disuelto', 'ion_nitrato'])->contains(fn ($campo) => $request->filled($campo));

            if ($tieneParametrosAgua) {
                ParametroAgua::create([
                    'piscina_id'       => $validated['piscina_id'],
                    'temperatura'      => $validated['temperatura'] ?? 0,
                    'ph'               => $validated['ph'] ?? 0,
                    'oxigeno_disuelto' => $validated['oxigeno_disuelto'] ?? 0,
                    'ion_nitrato'      => $validated['ion_nitrato'] ?? 0,
                    'fecha_medicion'   => $validated['fecha_medicion'],
                ]);
            }

            $tieneParametrosAmbientales = collect(['iluminancia', 'temperatura_ambiente', 'humedad_ambiente'])->contains(fn ($campo) => $request->filled($campo));

            if ($tieneParametrosAmbientales) {
                $datosAmbiente = [
                    'piscina_id'           => $validated['piscina_id'],
                    'iluminancia'          => $validated['iluminancia'] ?? 0,
                    'temperatura_ambiente' => $validated['temperatura_ambiente'] ?? 0,
                    'humedad_ambiente'     => $validated['humedad_ambiente'] ?? 0,
                    'fecha_medicion'       => $validated['fecha_medicion'],
                ];
                ParametroAmbiente::registrarLectura($datosAmbiente);
            }

            event(new ParametroAguaActualizado);

            return response()->json([
                'success' => true,
                'message' => 'Datos registrados correctamente.',
            ], 201);

        } catch (ValidationException $e) {

            Log::info('Los datos enviados no son válidos:', ['parametros' => $e->errors()]);

            return response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {

            Log::error('Error registrando parámetros', [
                'error'      => $e->getMessage(),
                'piscina_id' => $request->piscina_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al registrar los datos.'
            ], 500);
        }
    }
}
