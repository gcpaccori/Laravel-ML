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
        // \Log::info('Datos Recibidos', ['parametros' => $request->all()]);

        // Validar API Key
        if ($request->api_key == $this->apiKey) {
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

            echo "New record created successfully";

            // \Log::info('Exito', [
            //     'message' => 'Parámetros registrados correctamente.',
            //     'data' => $parametro
            // ]);
        }
        else{
            echo "Wrong API Key provided.";
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
