<?php

namespace App\DataTables;

use App\Models\Campania;
use App\Helpers\DataTableHelper;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

class CampaniaDataTable extends DataTable
{
    private function formatDate($date): string
    {
        return $date?->format('d/m/Y') ?? '-';
    }
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Campania> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->rawColumns(['estado','especies'])
            ->addColumn('action', function ($s) {
                return DataTableHelper::getAccionesPermitidasDelModulo($s->id);
            })
            ->addColumn('especies', function ($s) {
                $especies = $s->especies;
                if (!$especies || $especies->isEmpty()) {
                    return '<span class="badge badge-secondary">Sin especies</span>';
                }

                // Array de colores para los badges
                $colores = [
                    'badge-primary',
                    'badge-success',
                    'badge-info',
                    'badge-warning',
                    'badge-danger',
                    'badge-secondary',
                    'badge-dark',
                ];

                $badges = [];

                foreach ($s->especies as $index => $e) {
                    // Asignar color basado en el índice (rotativo)
                    $colorIndex = $index % count($colores);
                    $color = $colores[$colorIndex];

                    // Crear el badge
                    $badges[] = '<span class="badge ' . $color . ' me-1 mb-1">' .
                            e($e->especie->nombre) .
                            '</span>';
                }

                return implode(' ', $badges);
            })
            ->editColumn('piscigranja_id', function ($s) {
                return $s->piscigranja?->nombre ?? '-';
            })
            ->editColumn('estado', function ($s) {
                $estado = $s->estado;
                $badge = '<div class="badge badge-dark fw-bold">Error</div>';
                if ($estado  === 'en_proceso') {
                    $badge = '<div class="badge badge-success fw-bold">En Proceso</div>';
                }
                if ($estado  === 'finalizada') {
                    $badge = '<div class="badge badge-warning fw-bold">Finalizada</div>';
                }
                if ($estado  === 'cancelada') {
                    $badge = '<div class="badge badge-danger fw-bold">Cancelada</div>';
                }
                return $badge;
            })
            ->editColumn('fecha_inicio', fn($s) => $this->formatDate($s->fecha_inicio))
            ->editColumn('fecha_fin_estimada', fn($s) => $this->formatDate($s->fecha_fin_estimada))
            ->editColumn('fecha_fin_real', fn($s) => $this->formatDate($s->fecha_fin_real))
            ->setRowId('id');
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Campania>
     */
    public function query(Campania $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
                    ->setTableId('campania-table')
                    ->columns($this->getColumns())
                    ->minifiedAjax()
                    ->orderBy(1)
                    ->selectStyleSingle()
                    ->buttons([
                        Button::make('excel'),
                        Button::make('csv'),
                        Button::make('pdf'),
                        Button::make('print'),
                        Button::make('reset'),
                        Button::make('reload')
                    ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('id')->title('Item')->addClass('text-center'),
            Column::make('piscigranja_id')->title('Piscigranja')->addClass('min-w-175px'),
            Column::make('nombre')->title('Campaña')->addClass('min-w-175px'),
            Column::make('fecha_inicio')->title('F. Inicio')->addClass('min-w-100px'),
            Column::make('fecha_fin_estimada')->title('F. Fin Estimada')->addClass('min-w-150px'),
            Column::make('fecha_fin_real')->title('F. Fin Real')->addClass('min-w-125px'),
            Column::computed('etapas')->title('Etapas')->addClass('text-center'),
            Column::computed('especies')->title('Especies')->addClass('min-w-150px'),
            Column::make('estado')->title('Estado')->addClass('text-center'),
            Column::computed('action')->title('Acciones')->addClass('text-center min-w-100px')
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Campania_' . date('YmdHis');
    }
}
