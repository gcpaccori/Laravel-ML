<?php

use App\Http\Controllers\CalidadAguaController;
use App\Http\Controllers\HistorialAguaController;

// CALIDAD AGUA
Route::get('/calidadaguas', [CalidadAguaController::class, 'index'])->name('monitoreo.calidadaguas.index')->middleware('modulo:calidadagua');
Route::get('/calidadaguas/parametros', [CalidadAguaController::class, 'getDataParametros'])->name('calidadaguas.parametros');

// HISTORIAL AGUA
Route::get('/historialaguas', [HistorialAguaController::class, 'index'])->name('monitoreo.historialaguas.index')->middleware('modulo:historialagua');
Route::get('datatable/historialaguas', [HistorialAguaController::class, 'datatable'])->name('datatable.historialaguas');
Route::get('/chart-historialaguas', [HistorialAguaController::class, 'getChartData'])->name('chart.historialaguas');
