<?php

use App\Http\Controllers\EtapaController;
use App\Http\Controllers\EspecieController;
use App\Http\Controllers\PiscinaController;
use App\Http\Controllers\CampaniaController;
use App\Http\Controllers\PiscigranjaController;
use App\Http\Controllers\CampaniaEtapaController;
use App\Http\Controllers\CampaniaEspecieController;

// PISCIGRANJAS
Route::get('/piscigranjas', [PiscigranjaController::class, 'index'])->name('sispiscis.piscigranjas.index')->middleware('modulo:piscigranja');
Route::get('/datatable/piscigranjas', [PiscigranjaController::class, 'datatable'])->name('datatable.piscigranjas');
Route::post('/piscigranjas/store', [PiscigranjaController::class, 'store'])->name('piscigranjas.store');
Route::get('/piscigranjas/options', [PiscigranjaController::class, 'options'])->name('piscigranjas.options');

Route::get('/piscigranjas/{id}', [PiscigranjaController::class, 'edit'])->name('piscigranjas.edit');
Route::put('/piscigranjas/{id}', [PiscigranjaController::class, 'update'])->name('piscigranjas.update');
Route::delete('/piscigranjas/{id}', [PiscigranjaController::class, 'destroy'])->name('piscigranjas.destroy');
Route::get('/piscigranjas/{id}/piscinas', [PiscigranjaController::class, 'getPiscinas'])->name('piscigranjas.piscinas');

// PISCINAS
Route::get('/piscinas', [PiscinaController::class, 'index'])->name('sispiscis.piscinas.index')->middleware('modulo:piscina');
Route::get('datatable/piscinas', [PiscinaController::class, 'datatable'])->name('datatable.piscinas');
Route::post('/piscinas/store', [PiscinaController::class, 'store'])->name('piscinas.store');
Route::get('/piscinas/options', [PiscinaController::class, 'options'])->name('piscinas.options');

Route::get('/piscinas/{id}', [PiscinaController::class, 'edit'])->name('piscinas.edit');
Route::put('/piscinas/{id}', [PiscinaController::class, 'update'])->name('piscinas.update');
Route::delete('/piscinas/{id}', [PiscinaController::class, 'destroy'])->name('piscinas.destroy');

// ESPECIES
Route::get('/especies', [EspecieController::class, 'index'])->name('sispiscis.especies.index')->middleware('modulo:especie');
Route::get('datatable/especies', [EspecieController::class, 'datatable'])->name('datatable.especies');
Route::post('/especies/store', [EspecieController::class, 'store'])->name('especies.store');
Route::get('/especies/options', [EspecieController::class, 'options'])->name('especies.options');

Route::get('/especies/{id}', [EspecieController::class, 'edit'])->name('especies.edit');
Route::put('/especies/{id}', [EspecieController::class, 'update'])->name('especies.update');
Route::delete('/especies/{id}', [EspecieController::class, 'destroy'])->name('especies.destroy');

// CAMPAÑAS
Route::get('/campanias', [CampaniaController::class, 'index'])->name('sispiscis.campanias.index')->middleware('modulo:campania');
Route::get('datatable/campanias', [CampaniaController::class, 'datatable'])->name('datatable.campanias');
Route::post('/campanias/store', [CampaniaController::class, 'store'])->name('campanias.store');
Route::get('/campanias/options', [CampaniaController::class, 'options'])->name('campanias.options');

Route::get('/campanias/{id}', [CampaniaController::class, 'edit'])->name('campanias.edit');
Route::put('/campanias/{id}', [CampaniaController::class, 'update'])->name('campanias.update');
Route::delete('/campanias/{id}', [CampaniaController::class, 'destroy'])->name('campanias.destroy');

// ETAPAS
Route::get('/etapas/options', [EtapaController::class, 'options'])->name('etapas.options');

// CAMPAÑA ESPECIE
Route::delete('/campanias-especies/{reg}', [CampaniaEspecieController::class, 'destroy'])->name('campanias.especies.destroy');

//CAMPAÑA ETAPAS
Route::post('/campanias-etapas/store', [CampaniaEtapaController::class, 'store'])->name('campanias.etapas.store');

Route::get('/campanias-etapas/options/{campania_especie_id}', [CampaniaEtapaController::class, 'options'])->name('campanias.etapas.options');
Route::get('/campanias-etapas/{campania_id}', [CampaniaEtapaController::class, 'create'])->name('campanias.etapas.create');
Route::get('/campanias-etapas/{id}', [CampaniaEtapaController::class, 'edit'])->name('campanias.etapas.edit');
Route::put('/campanias-etapas/{id}', [CampaniaEtapaController::class, 'update'])->name('campanias.etapas.update');
Route::delete('/campanias-etapas/{id}', [CampaniaEtapaController::class, 'destroy'])->name('campanias.etapas.destroy');
