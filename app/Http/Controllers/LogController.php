<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\LogService;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\DataTables\LogsDataTable;

class LogController extends Controller
{
    protected $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    public function index()
    {
        $datatable = new LogsDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        $logs = $this->logService->getLogs();
        $stats = $this->logService->getLogStats();

        return Inertia::render('Seguridad/Logs', [
            'title' => 'Listado de Logs',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            'columns' => $columns,
            'stats' => $stats,
        ]);
    }

    public function datatable(LogsDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    public function show($id)
    {
        $log = $this->logService->getLogById($id);

        if (!$log) {
            return response()->json([
                'error' => 'Log no encontrado'
            ], 404);
        }

        return response()->json($log);
    }

    public function clear()
    {
        try {
            $this->logService->clearLogs();

            return response()->json([
                'success' => true,
                'message' => 'Logs limpiados correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar los logs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function download()
    {
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            abort(404, 'Archivo de logs no encontrado');
        }

        return response()->download(
            $logPath,
            'laravel_' . date('Y-m-d_His') . '.log'
        );
    }
}
