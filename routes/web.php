<?php

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\RoleController;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Controllers\AccionController;
use App\Http\Controllers\ModuloController;
use App\Http\Controllers\SistemaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SessionLogController;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

Route::redirect('/', 'login');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // SEGURIDAD - SISTEMAS
    Route::get('seguridad/sistemas', [SistemaController::class, 'index'])->name('seguridad.sistemas.index')->middleware('modulo:sistema');
    Route::get('datatable/sistemas', [SistemaController::class, 'datatable'])->name('datatable.sistemas');
    Route::post('seguridad/sistemas/store', [SistemaController::class, 'store'])->name('seguridad.sistemas.store');
    Route::get('seguridad/sistemas/options', [SistemaController::class, 'options'])->name('seguridad.sistemas.options');

    Route::delete('seguridad/sistemas/{id}', [SistemaController::class, 'destroy'])->name('seguridad.sistemas.destroy');
    Route::get('seguridad/sistemas/{id}', [SistemaController::class, 'edit'])->name('seguridad.sistemas.edit');
    Route::put('seguridad/sistemas/{id}', [SistemaController::class, 'update'])->name('seguridad.sistemas.update');

    // SEGURIDAD - ACCIONES
    Route::get('seguridad/accions', [AccionController::class, 'index'])->name('seguridad.accions.index')->middleware('modulo:accion');
    Route::get('datatable/accions', [AccionController::class, 'datatable'])->name('datatable.accions');
    Route::post('seguridad/accions/store', [AccionController::class, 'store'])->name('seguridad.accions.store');
    Route::get('seguridad/accions/options', [AccionController::class, 'options'])->name('seguridad.accions.options');

    Route::delete('seguridad/accions/{id}', [AccionController::class, 'destroy'])->name('seguridad.accions.destroy');
    Route::get('seguridad/accions/{id}', [AccionController::class, 'edit'])->name('seguridad.accions.edit');
    Route::put('seguridad/accions/{id}', [AccionController::class, 'update'])->name('seguridad.accions.update');

    // SEGURIDAD - MODULOS
    Route::get('seguridad/modulos', [ModuloController::class, 'index'])->name('seguridad.modulos.index')->middleware('modulo:modulo');
    Route::get('datatable/modulos', [ModuloController::class, 'datatable'])->name('datatable.modulos');
    Route::post('seguridad/modulos/store', [ModuloController::class, 'store'])->name('seguridad.modulos.store');

    Route::get('seguridad/padres/{id}', [ModuloController::class, 'showPadre'])->name('seguridad.padres');
    Route::delete('seguridad/modulos/{id}', [ModuloController::class, 'destroy'])->name('seguridad.modulos.destroy');
    Route::get('seguridad/modulos/{id}', [ModuloController::class, 'edit'])->name('seguridad.modulos.edit');
    Route::put('seguridad/modulos/{id}', [ModuloController::class, 'update'])->name('seguridad.modulos.update');

    // SEGURIDAD - ROLES
    Route::get('seguridad/roles', [RoleController::class, 'index'])->name('seguridad.roles.index')->middleware('modulo:role');;
    Route::get('datatable/roles', [RoleController::class, 'datatable'])->name('datatable.roles');
    Route::post('seguridad/roles/store', [RoleController::class, 'store'])->name('seguridad.roles.store');
    Route::get('/seguridad/permisos/arbol', [RoleController::class, 'estructuraPermisos'])->name('seguridad.permisos.arbol');
    Route::get('seguridad/permisos/options', [RoleController::class, 'options'])->name('seguridad.roles.options');

    Route::get('seguridad/roles/{id}', [RoleController::class, 'edit'])->name('seguridad.roles.edit');
    Route::put('seguridad/roles/{id}', [RoleController::class, 'update'])->name('seguridad.roles.update');
    Route::delete('seguridad/roles/{id}', [RoleController::class, 'destroy'])->name('seguridad.roles.destroy');

    // SEGURIDAD - USUARIOS
    Route::get('seguridad/usuarios', [UsuarioController::class, 'index'])->name('seguridad.usuarios.index')->middleware('modulo:usuario');
    Route::get('datatable/usuarios', [UsuarioController::class, 'datatable'])->name('datatable.usuarios');
    Route::post('seguridad/usuarios/store', [UsuarioController::class, 'store'])->name('seguridad.usuarios.store');
    Route::get('seguridad/usuarios/options', [UsuarioController::class, 'options'])->name('seguridad.usuarios.options');

    Route::get('seguridad/usuarios/{id}', [UsuarioController::class, 'edit'])->name('seguridad.usuarios.edit');
    Route::put('seguridad/usuarios/{id}', [UsuarioController::class, 'update'])->name('seguridad.usuarios.update');
    Route::delete('seguridad/usuarios/{id}', [UsuarioController::class, 'destroy'])->name('seguridad.usuarios.destroy');

    // SESSION LOG
    Route::get('column/sessionlogs', [SessionLogController::class, 'columns'])->name('column.sessionlogs');
    Route::get('datatable/sessionlogs', [SessionLogController::class, 'datatable'])->name('datatable.sessionlogs');

});
