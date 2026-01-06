<?php

namespace App\DataTables;

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\LogService;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Illuminate\Support\Collection;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;

class LogsDataTable extends DataTable
{
    protected $logService;

    public function __construct()
    {
        $this->logService = app(LogService::class);
    }

    /**
     * Build the DataTable class.
     */
    public function dataTable($query): CollectionDataTable
    {
        return (new CollectionDataTable($query))
            ->editColumn('datetime', function ($log) {
                return Carbon::parse($log['datetime'])->format('d/m/Y H:i:s');
            })
            ->editColumn('level', function ($log) {
                $colors = [
                    'ERROR' => 'danger',
                    'WARNING' => 'warning',
                    'INFO' => 'info',
                    'DEBUG' => 'secondary',
                    'CRITICAL' => 'dark',
                    'EMERGENCY' => 'danger',
                ];

                $color = $colors[$log['level']] ?? 'primary';
                return '<span class="badge badge-' . $color . '">' . $log['level'] . '</span>';
            })
            ->editColumn('message', function ($log) {
                $messageId = 'log-message-' . $log['id'];
                $previewId = 'preview-' . $messageId;
                $preview = Str::limit($log['message'], 300, '...');
                $fullMessage = htmlspecialchars($log['message']);

                // Solo mostrar botón si el mensaje es más largo que el preview
                $needsToggle = strlen($log['message']) > 300;

                return '
                    <div class="log-message-container">
                        <div class="log-message-preview" id="' . $previewId . '">
                            ' . nl2br(htmlspecialchars($preview)) . '
                        </div>
                        ' . ($needsToggle ? '
                        <div class="log-message-full collapse" id="' . $messageId . '">
                            <pre class="bg-light p-2 rounded mt-2 mb-0" style="font-size: 0.85rem; max-height: 400px; overflow-y: auto;">' . $fullMessage . '</pre>
                        </div>
                        <button
                            class="btn btn-sm btn-link toggle-message p-0 mt-1"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#' . $messageId . '"
                            data-preview-id="' . $previewId . '"
                            aria-expanded="false"
                            aria-controls="' . $messageId . '">
                            <i class="bi bi-search"></i> Ver completo
                        </button>
                        ' : '') . '
                    </div>
                ';
            })
            ->rawColumns(['level', 'message'])
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     */
    public function query(): Collection
    {
        $logs = $this->logService->getLogs();

        // Filtrar por nivel si se especifica
        if (request()->has('level') && request('level') !== '') {
            $logs = $logs->filter(function ($log) {
                return $log['level'] === strtoupper(request('level'));
            });
        }

        // Filtrar por fecha desde
        if (request()->has('fecha_desde') && request('fecha_desde') !== '') {
            $fechaDesde = Carbon::parse(request('fecha_desde'))->startOfDay();
            $logs = $logs->filter(function ($log) use ($fechaDesde) {
                return Carbon::parse($log['datetime'])->gte($fechaDesde);
            });
        }

        // Filtrar por fecha hasta
        if (request()->has('fecha_hasta') && request('fecha_hasta') !== '') {
            $fechaHasta = Carbon::parse(request('fecha_hasta'))->endOfDay();
            $logs = $logs->filter(function ($log) use ($fechaHasta) {
                return Carbon::parse($log['datetime'])->lte($fechaHasta);
            });
        }

        // Búsqueda en mensaje
        if (request()->has('search') && request('search.value') !== '') {
            $searchValue = strtolower(request('search.value'));
            $logs = $logs->filter(function ($log) use ($searchValue) {
                return str_contains(strtolower($log['message']), $searchValue) ||
                       str_contains(strtolower($log['level']), $searchValue) ||
                       str_contains(strtolower($log['environment']), $searchValue);
            });
        }

        return $logs;
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('logs-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(1, 'desc') // Ordenar por fecha descendente
            ->selectStyleSingle();
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')
                ->title('Item')
                ->addClass('text-center')
                ->width(60),

            Column::make('datetime')
                ->title('Fecha y Hora')
                ->addClass('text-center min-w-150px'),

            Column::make('environment')
                ->title('Ambiente')
                ->addClass('text-center min-w-100px'),

            Column::make('level')
                ->title('Nivel')
                ->addClass('text-center min-w-100px'),

            Column::make('message')
                ->title('Mensaje')
                ->addClass('min-w-300px'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Logs_' . date('YmdHis');
    }
}
