<?php

use App\Http\Controllers\AlimentacionBftController;
use App\Http\Controllers\BiometriaController;

// BIOMETRIAS
Route::get('/biometrias', [BiometriaController::class, 'index'])->name('produccion.biometrias.index')->middleware('modulo:biometria');
Route::get('/datatable/biometrias', [BiometriaController::class, 'datatable'])->name('datatable.biometrias');
Route::get('/biometrias/create', [BiometriaController::class, 'create'])->name('biometrias.create');
Route::post('/biometrias/store', [BiometriaController::class, 'store'])->name('biometrias.store');
Route::get('/biometrias/exportar-excel', [BiometriaController::class, 'exportarExcel'])->name('biometrias.excel');
Route::get('/biometrias/exportar-csv', [BiometriaController::class, 'exportarCsv'])->name('biometrias.csv');

Route::get('biometrias/alimento-referencial', [BiometriaController::class, 'alimentoReferencial'])->name('biometrias.alimento-referencial');
Route::get('/biometrias/{id}', [BiometriaController::class, 'show'])->name('biometrias.show');
Route::get('/biometrias/edit/{id}', [BiometriaController::class, 'edit'])->name('biometrias.edit');
Route::put('/biometrias/update/{id}', [BiometriaController::class, 'update'])->name('biometrias.update');
Route::delete('/biometrias/destroy/{id}', [BiometriaController::class, 'destroy'])->name('biometrias.destroy');
Route::get('biometrias/{biometria}/exportar-pdf', [BiometriaController::class, 'exportarPdf'])->name('biometrias.pdf');
Route::get('biometrias/{biometria}/exportar-excel', [BiometriaController::class, 'exportarExcelBiometria'])->name('biometrias.excel.biometria');


Route::get('/piscigranjas/campania/{piscigranja_id}', [BiometriaController::class, 'showCampania'])->name('campania.active.show');
Route::get('/campania/especie/{campania_id}', [BiometriaController::class, 'showEspecie'])->name('especie.active.show');
Route::get('/especie/etapa/{campania_especie_id}', [BiometriaController::class, 'showEtapa'])->name('etapa.active.show');
Route::get('/etapa/parametro/{campania_etapa_id}', [BiometriaController::class, 'showParametrosEtapa'])->name('parametro.active.show');

Route::get('alimentacion-bft', [AlimentacionBftController::class, 'index'])->name('produccion.alimentacionbfts.index')->middleware('modulo:alimentacionbft');
Route::get('alimentacion-bft/datatable', [AlimentacionBftController::class, 'datatable'])->name('alimentacionbfts.datatable');
Route::get('alimentacion-bft/{campaniaEspecie}/create', [AlimentacionBftController::class, 'create'])->name('alimentacionbfts.create');
Route::post('alimentacion-bft/{campaniaEspecie}/store', [AlimentacionBftController::class, 'store'])->name('alimentacionbfts.store');
Route::get('alimentacion-bft/{campaniaEspecie}/show', [AlimentacionBftController::class, 'show'])->name('alimentacionbfts.show');
Route::get('alimentacion-bft/{campaniaEspecie}/pdf', [AlimentacionBftController::class, 'pdf'])->name('alimentacionbfts.pdf');
Route::get('alimentacion-bft/{campaniaEspecie}/edit', [AlimentacionBftController::class, 'edit'])->name('alimentacionbfts.edit');
Route::get('alimentacion-bft/{campaniaEspecie}/excel', [AlimentacionBftController::class, 'excel'])->name('alimentacionbfts.excel');
