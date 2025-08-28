<?php

namespace App\Http\Controllers\Api;

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
                return response()->json(['error' => 'Clave API incorrecta proporcionada.'], 401);
            }

            $parametro = ParametroAgua::create([
                'piscina_id'        => $request['piscina_id'],
                'temperatura'       => $request['temperatura'],
                'ph'                => $request['ph'],
                'oxigeno_disuelto'  => $request['oxigeno_disuelto'],
                'ion_nitrato'       => $request['ion_nitrato'],
                'fecha_medicion'    => $request['fecha_medicion']
            ]);

            event(new ParametroAguaActualizado($parametro));

            return response()->json([
                'message' => 'Parámetros registrados correctamente.',
                'data' => $parametro
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error',
                'message' => $e->getMessage()
            ], 500);
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
