<?php

use App\Http\Controllers\BiometriaController;

// BIOMETRIAS
Route::get('/biometrias', [BiometriaController::class, 'index'])->name('produccion.biometrias.index')->middleware('modulo:biometria');
Route::get('/datatable/biometrias', [BiometriaController::class, 'datatable'])->name('datatable.biometrias');
Route::post('/biometrias/store', [BiometriaController::class, 'store'])->name('biometrias.store');
// Route::get('/piscigranjas/options', [PiscigranjaController::class, 'options'])->name('piscigranjas.options');

Route::get('/biometrias/edit/{id}', [BiometriaController::class, 'edit'])->name('biometrias.edit');
Route::put('/biometrias/update/{id}', [BiometriaController::class, 'update'])->name('biometrias.update');
Route::delete('/biometrias/destroy/{id}', [BiometriaController::class, 'destroy'])->name('biometrias.destroy');

Route::get('/piscigranjas/campania/{piscigranja_id}', [BiometriaController::class, 'showCampania'])->name('campania.active.show');
Route::get('/campania/especie/{campania_id}', [BiometriaController::class, 'showEspecie'])->name('especie.active.show');
Route::get('/especie/etapa/{campania_especie_id}', [BiometriaController::class, 'showEtapa'])->name('etapa.active.show');
Route::get('/etapa/parametro/{campania_etapa_id}', [BiometriaController::class, 'showParametrosEtapa'])->name('parametro.active.show');
