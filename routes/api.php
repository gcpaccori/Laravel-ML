<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UbigeoController;
use App\Http\Controllers\Api\ParametroAguaController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('departamentos', [UbigeoController::class, 'departamentos'])->name('api.departamentos');
Route::get('provincias/{department_id}', [UbigeoController::class, 'provincias'])->name('api.provincias');
Route::get('distritos/{province_id}', [UbigeoController::class, 'distritos'])->name('api.distritos');


// RUTAS SENSORES
Route::post('/parametros-agua', [ParametroAguaController::class, 'store'])->name('parametro.agua.store');
