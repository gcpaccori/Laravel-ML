<?php

use App\Http\Controllers\PiscinaController;
use App\Http\Controllers\CampaniaController;
use App\Http\Controllers\PiscigranjaController;

// PISCIGRANJAS
Route::get('/piscigranjas', [PiscigranjaController::class, 'index'])->name('sispiscis.piscigranjas.index')->middleware('modulo:piscigranja');
Route::get('/datatable/piscigranjas', [PiscigranjaController::class, 'datatable'])->name('datatable.piscigranjas');
Route::post('/piscigranjas/store', [PiscigranjaController::class, 'store'])->name('piscigranjas.store');

Route::get('/piscigranjas/{id}', [PiscigranjaController::class, 'edit'])->name('piscigranjas.edit');
Route::put('/piscigranjas/{id}', [PiscigranjaController::class, 'update'])->name('piscigranjas.update');
Route::delete('/piscigranjas/{id}', [PiscigranjaController::class, 'destroy'])->name('piscigranjas.destroy');

// PISCINAS
Route::get('/piscinas', [PiscinaController::class, 'index'])->name('sispiscis.piscinas.index')->middleware('modulo:piscina');
Route::get('datatable/piscinas', [PiscinaController::class, 'datatable'])->name('datatable.piscinas');

// CAMPAÑAS
Route::get('/campanias', [CampaniaController::class, 'index'])->name('sispiscis.campanias.index')->middleware('modulo:campania');
Route::get('datatable/campanias', [CampaniaController::class, 'datatable'])->name('datatable.campanias');
