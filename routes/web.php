<?php

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::redirect('/', 'login');

Route::middleware(['auth:sanctum', config('jetstream.auth_session'),'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/get-data', [DashboardController::class, 'getData'])->name('dashboard.get.data');

    Route::get('/server-time', function () {
        return response()->json([
            'time' => Carbon::now()->toISOString(), // ISO 8601
        ]);
    })->name('server.time');

    // S. SEGURIDAD
    require __DIR__.'/system/seguridad.php';

    // S. PISCIGRANJAS
    require __DIR__.'/system/sispiscis.php';

    // S. MONITOREO
    require __DIR__.'/system/monitoreo.php';

    // S. PRODUCCION
    require __DIR__.'/system/produccion.php';

});
