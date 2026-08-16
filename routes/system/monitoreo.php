<?php

use App\Http\Controllers\CalidadAguaController;
use App\Http\Controllers\HistorialAguaController;
use App\Http\Controllers\ModeloMlController;
use App\Http\Controllers\ParametroAmbienteController;

// CALIDAD AGUA
Route::get('/calidadaguas', [CalidadAguaController::class, 'index'])->name('monitoreo.calidadaguas.index')->middleware('modulo:calidadagua');
Route::get('/calidadaguas/parametros', [CalidadAguaController::class, 'getDataParametros'])->name('calidadaguas.parametros');

// HISTORIAL AGUA
Route::get('/historialaguas', [HistorialAguaController::class, 'index'])->name('monitoreo.historialaguas.index')->middleware('modulo:historialagua');
Route::get('datatable/historialaguas', [HistorialAguaController::class, 'datatable'])->name('datatable.historialaguas');
Route::get('/chart-historialaguas', [HistorialAguaController::class, 'getChartData'])->name('chart.historialaguas');
Route::get('/parametros-agua/csv', [HistorialAguaController::class, 'export_csv'])->name('parametrosagua.csv');
Route::get('/parametros-agua/excel', [HistorialAguaController::class, 'export_excel'])->name('parametrosagua.excel');

// MODELOS ML
Route::get('/modelos-ml', [ModeloMlController::class, 'index'])->name('monitoreo.modelosmls.index')->middleware('modulo:modelosml');
Route::get('/gemelo-digital', [ModeloMlController::class, 'gemeloDigital'])->name('monitoreo.gemelodigitals.index')->middleware('modulo:modelosml');
Route::get('/modelos-ml/proyecciones', [ModeloMlController::class, 'proyecciones'])->name('monitoreo.modelosmls.proyecciones')->middleware('modulo:modelosml');
Route::post('/modelos-ml/simulacion', [ModeloMlController::class, 'simulacion'])->name('monitoreo.modelosmls.simulacion')->middleware('modulo:modelosml');
Route::get('/modelos-ml-suite', [ModeloMlController::class, 'suite'])->name('monitoreo.modelosmlsuites.index')->middleware('modulo:modelosmlsuite');

Route::get('/fotoperiodo', [ParametroAmbienteController::class, 'index'])->name('monitoreo.fotoperiodos.index')->middleware('modulo:fotoperiodo');
Route::get('/fotoperiodo/parametros', [ParametroAmbienteController::class, 'getDataParametros'])->name('fotoperiodos.parametros');

