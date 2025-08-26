<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class UbigeoController extends Controller
{
    public function departamentos() : JsonResponse
    {
        $departamentos = DB::table('ubigeo_departamentos')->orderBy('name')->get();
        return response()->json($departamentos);
    }

    public function provincias($department_id): JsonResponse
    {
        $provincias = DB::table('ubigeo_provincias')
            ->where('departamento_id', $department_id)
            ->orderBy('name')
            ->get();
        return response()->json($provincias);
    }

    public function distritos($province_id): JsonResponse
    {
        $distritos = DB::table('ubigeo_distritos')
            ->where('provincia_id', $province_id)
            ->orderBy('name')
            ->get();
        return response()->json($distritos);
    }
}
