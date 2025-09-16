<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\ParametroAgua;
use App\Http\Controllers\Controller;
use App\Events\ParametroAguaActualizado;

class ParametroAguaController extends Controller
{

    private $apiKey = "MonitoreoAgua2025"; // API key

    /**
     * Display a listing of the resource.
     */
    public function index()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validar API Key
            if ($request->api_key !== $this->apiKey) {
                // return response()->json(['error' => 'Clave API incorrecta proporcionada.'], 401);
                return 'Clave API incorrecta proporcionada.';
            }

            \Log::info('Datos Recibidos', ['parametros' => $request]);

            // Normalizar fecha: de "Y-d-m" a "Y-m-d"
            // $fechaFormateada = Carbon::createFromFormat('Y-d-m H:i:s', $request->fecha_medicion)->format('Y-m-d H:i:s');
            $fechaFormateada = Carbon::createFromFormat('d/m/Y H:i:s', $request->fecha_medicion)->format('Y-m-d H:i:s');

            $parametro = ParametroAgua::create([
                'piscina_id'        => $request->piscina_id,
                'temperatura'       => $request->temperatura,
                'ph'                => $request->ph,
                'oxigeno_disuelto'  => $request->oxigeno_disuelto,
                'ion_nitrato'       => $request->ion_nitrato,
                'fecha_medicion'    => $fechaFormateada,
            ]);

            event(new ParametroAguaActualizado($parametro));

            \Log::info('Exito', [
                'message' => 'Parámetros registrados correctamente.',
                'data' => $parametro
            ]);
            
        } catch (\Exception $e) {
            \Log::info('Error', [
                'error' => 'Error',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
