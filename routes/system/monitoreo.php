<?php

use App\Http\Controllers\CalidadAguaController;

Route::get('/calidadaguas', [CalidadAguaController::class, 'index'])->name('monitoreo.calidadaguas.index')->middleware('modulo:calidadagua');
Route::get('/calidadaguas/parametros', [CalidadAguaController::class, 'getDataParametros'])->name('calidadaguas.parametros');

