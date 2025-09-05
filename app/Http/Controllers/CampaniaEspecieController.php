<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CampaniaEspecie;

class CampaniaEspecieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
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
    public function destroy( CampaniaEspecie $reg )
    {
        if ( $reg->etapas()->exists() ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar. Este registro tiene datos relacionados.',
            ]);
        }

        $reg->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado correctamente.',
        ]);
    }
}
